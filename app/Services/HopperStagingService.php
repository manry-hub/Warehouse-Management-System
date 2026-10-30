<?php

namespace App\Services;

use App\Models\HopperTransaction;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\MaterialLot;
use App\Models\StagingRequest;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class HopperStagingService
{
    /**
     * Verify recipe compatibility between a scanned lot and the active work order on a machine.
     *
     * @return array<string, mixed>
     */
    public function verifyRecipeCompatibility(string $machineCode, string $lotNumber, ?string $productionOrder = null): array
    {
        $lot = MaterialLot::with('item')->where('lot_number', trim($lotNumber))->first();
        if (! $lot) {
            return [
                'compatible' => false,
                'message' => "Material lot [{$lotNumber}] does not exist.",
            ];
        }

        if ($lot->status !== MaterialLot::STATUS_AVAILABLE) {
            return [
                'compatible' => false,
                'message' => "Lot [{$lotNumber}] cannot be staged. Current status: [{$lot->status}] (Must be AVAILABLE).",
            ];
        }

        // Check if there is an active/pending staging request for this machine or order
        $stagingQuery = StagingRequest::with('items.item')
            ->where(function ($q) use ($machineCode, $productionOrder) {
                $q->where('target_machine_code', $machineCode);
                if ($productionOrder) {
                    $q->orWhere('mes_production_order_id', $productionOrder);
                }
            })
            ->whereIn('status', [
                StagingRequest::STATUS_PENDING,
                StagingRequest::STATUS_ALLOCATED,
                StagingRequest::STATUS_PICKED,
                StagingRequest::STATUS_DISPATCHED_TO_MES,
                StagingRequest::STATUS_COMPLETED,
            ]);

        $activeRequest = $stagingQuery->latest()->first();

        // If an active staging request exists, enforce SKU recipe matching
        if ($activeRequest && $activeRequest->items->isNotEmpty()) {
            $authorizedItemIds = $activeRequest->items->pluck('item_id')->toArray();

            if (! in_array($lot->item_id, $authorizedItemIds)) {
                $authorizedSkus = $activeRequest->items->map(fn ($i) => $i->item->sku)->join(', ');

                return [
                    'compatible' => false,
                    'machine_code' => $machineCode,
                    'order_number' => $activeRequest->mes_production_order_id,
                    'scanned_sku' => $lot->item->sku,
                    'required_skus' => $authorizedSkus,
                    'message' => "RECIPE MISMATCH: Scanned material [{$lot->item->sku}] is not authorized for machine [{$machineCode}]. Order requires: [{$authorizedSkus}].",
                ];
            }

            $orderNumber = $activeRequest->mes_production_order_id;
        } else {
            // General verification: Ensure the material is a raw resin, regrind, or masterbatch
            if (! $lot->item->isRawMaterial() && $lot->lot_type !== MaterialLot::TYPE_REGRIND) {
                return [
                    'compatible' => false,
                    'message' => "Item [{$lot->item->sku}] is not a raw material or regrind suitable for machine hoppers.",
                ];
            }

            $orderNumber = $productionOrder ?? "WO-AUTO-{$machineCode}-".date('Ymd');
        }

        $totalStock = InventoryBalance::where('material_lot_id', $lot->id)->sum('quantity_on_hand');

        return [
            'compatible' => true,
            'machine_code' => $machineCode,
            'order_number' => $orderNumber,
            'lot_number' => $lot->lot_number,
            'material_code' => $lot->item->sku,
            'material_name' => $lot->item->name,
            'lot_type' => $lot->lot_type,
            'quantity_on_hand' => (float) $totalStock,
            'message' => "Lot verified. Compatible with machine [{$machineCode}] recipe.",
        ];
    }

    /**
     * Issue raw material lot to a machine staging hopper with double-entry ledger balance updates.
     */
    public function issueToHopper(
        string $lotNumber,
        string $machineCode,
        float $quantityKg,
        ?int $userId = null,
        ?string $orderNumber = null
    ): HopperTransaction {
        if ($quantityKg <= 0) {
            throw new InvalidArgumentException('Staged quantity must be greater than zero.');
        }

        $verification = $this->verifyRecipeCompatibility($machineCode, $lotNumber, $orderNumber);
        if (! $verification['compatible']) {
            throw new RuntimeException($verification['message']);
        }

        $resolvedOrder = $verification['order_number'];

        return DB::transaction(function () use ($lotNumber, $machineCode, $quantityKg, $userId, $resolvedOrder) {
            $lot = MaterialLot::with('item')->where('lot_number', trim($lotNumber))->lockForUpdate()->firstOrFail();

            // 1. Locate source bin holding available balance for this lot
            $sourceBalance = InventoryBalance::with('bin')
                ->where('material_lot_id', $lot->id)
                ->where('quantity_on_hand', '>=', $quantityKg)
                ->lockForUpdate()
                ->first();

            if (! $sourceBalance) {
                $totalAvailable = InventoryBalance::where('material_lot_id', $lot->id)->sum('quantity_on_hand');
                throw new RuntimeException("Insufficient stock in any single bin for lot [{$lotNumber}]. Requested: {$quantityKg} kg, Max available: {$totalAvailable} kg.");
            }

            $sourceBin = $sourceBalance->bin;

            // 2. Locate or resolve the hopper bin for this machine
            $hopperBin = WarehouseBin::whereHas('zone', function ($q) {
                $q->where('zone_type', WarehouseZone::TYPE_STAGING_BUFFER);
            })->where(function ($q) use ($machineCode) {
                $cleanMachine = str_replace(['-', '_', ' '], '', strtoupper($machineCode));
                $q->where('bin_code', $machineCode)
                    ->orWhere('bin_code', 'STG-'.$machineCode)
                    ->orWhere('bin_code', 'like', "%{$cleanMachine}%")
                    ->orWhere('bin_code', 'like', "%HOPPER-{$cleanMachine}%");
            })->first();

            if (! $hopperBin) {
                // Fallback to any staging bin or create a dedicated machine hopper bin
                $stagingZone = WarehouseZone::where('zone_type', WarehouseZone::TYPE_STAGING_BUFFER)->first();
                if (! $stagingZone) {
                    throw new RuntimeException('No staging buffer zone configured in topology.');
                }

                $hopperBin = WarehouseBin::firstOrCreate(
                    ['bin_code' => "HOPPER-{$machineCode}"],
                    [
                        'zone_id' => $stagingZone->id,
                        'aisle' => 'HOP',
                        'rack' => 'MACH',
                        'shelf' => '1',
                        'bin' => '1',
                        'max_weight_kg' => 2000.0,
                        'max_volume_cbm' => 5.0,
                        'is_blocked' => false,
                    ]
                );
            }

            // 3. Deduct from source bin balance
            $sourceBalance->quantity_on_hand = (float) $sourceBalance->quantity_on_hand - $quantityKg;
            $sourceBalance->save();

            // 4. Deposit into hopper bin balance
            $hopperBalance = InventoryBalance::where('bin_id', $hopperBin->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $hopperBalance) {
                $hopperBalance = new InventoryBalance([
                    'bin_id' => $hopperBin->id,
                    'item_id' => $lot->item_id,
                    'material_lot_id' => $lot->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]);
            }

            $hopperBalance->quantity_on_hand = (float) $hopperBalance->quantity_on_hand + $quantityKg;
            $hopperBalance->save();

            $transactionCode = 'HOP-'.date('Ymd').'-'.strtoupper(Str::random(4));

            // 5. Commit atomic double-entry movement
            InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => 'HOPPER_STAGE',
                'item_id' => $lot->item_id,
                'material_lot_id' => $lot->id,
                'source_bin_id' => $sourceBin->id,
                'destination_bin_id' => $hopperBin->id,
                'quantity' => $quantityKg,
                'uom' => $lot->item->unit_of_measure ?? 'KG',
                'reference_type' => 'HOPPER_STAGE',
                'reference_id' => $transactionCode,
                'user_id' => $userId,
                'notes' => "Staged to machine hopper [{$machineCode}] for order [{$resolvedOrder}]",
            ]);

            // 6. Record HopperTransaction
            $transaction = HopperTransaction::create([
                'transaction_code' => $transactionCode,
                'machine_code' => $machineCode,
                'hopper_bin_id' => $hopperBin->id,
                'material_lot_id' => $lot->id,
                'operator_id' => $userId,
                'production_order_number' => $resolvedOrder,
                'quantity_staged_kg' => $quantityKg,
                'quantity_returned_kg' => 0.000,
                'status' => HopperTransaction::STATUS_STAGED,
                'staged_at' => now(),
            ]);

            Log::info("HOPPER STAGED [{$transactionCode}]: Staged {$quantityKg} kg of [{$lot->lot_number}] to [{$hopperBin->bin_code}]");

            return $transaction;
        });
    }

    /**
     * Return leftover unconsumed partial resin from machine hopper back to warehouse inventory.
     *
     * @return array<string, mixed>
     */
    public function returnPartialFromHopper(
        string $transactionCodeOrId,
        float $returnedWeightKg,
        ?string $targetBinCode = null,
        ?int $userId = null
    ): array {
        if ($returnedWeightKg <= 0) {
            throw new InvalidArgumentException('Returned weight must be greater than zero.');
        }

        return DB::transaction(function () use ($transactionCodeOrId, $returnedWeightKg, $targetBinCode, $userId) {
            $transaction = HopperTransaction::with(['materialLot.item', 'hopperBin'])
                ->where('transaction_code', $transactionCodeOrId)
                ->orWhere('id', $transactionCodeOrId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status === HopperTransaction::STATUS_CLOSED) {
                throw new RuntimeException("Hopper transaction [{$transaction->transaction_code}] is already closed.");
            }

            $maxReturnable = (float) $transaction->quantity_staged_kg - (float) $transaction->quantity_returned_kg;
            if ($returnedWeightKg > $maxReturnable) {
                throw new RuntimeException("Returned weight ({$returnedWeightKg} kg) exceeds staged remainder ({$maxReturnable} kg).");
            }

            $lot = $transaction->materialLot;
            $hopperBin = $transaction->hopperBin;

            // Verify hopper balance
            $hopperBalance = InventoryBalance::where('bin_id', $hopperBin->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $hopperBalance || $hopperBalance->quantity_on_hand < $returnedWeightKg) {
                $avail = $hopperBalance ? $hopperBalance->quantity_on_hand : 0;
                throw new RuntimeException("Hopper bin [{$hopperBin->bin_code}] has insufficient balance ({$avail} kg) for return of {$returnedWeightKg} kg.");
            }

            // Find target raw material bin
            if ($targetBinCode) {
                $targetBin = WarehouseBin::where('bin_code', $targetBinCode)->firstOrFail();
            } else {
                $targetBin = WarehouseBin::whereHas('zone', function ($q) {
                    $q->where('zone_type', WarehouseZone::TYPE_RAW_MATERIAL);
                })->where('is_blocked', false)->first();

                if (! $targetBin) {
                    throw new RuntimeException('No open raw material bin found for return putaway.');
                }
            }

            // Deduct from hopper balance
            $hopperBalance->quantity_on_hand = (float) $hopperBalance->quantity_on_hand - $returnedWeightKg;
            $hopperBalance->save();

            // Deposit to target raw bin
            $targetBalance = InventoryBalance::where('bin_id', $targetBin->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $targetBalance) {
                $targetBalance = new InventoryBalance([
                    'bin_id' => $targetBin->id,
                    'item_id' => $lot->item_id,
                    'material_lot_id' => $lot->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]);
            }

            $targetBalance->quantity_on_hand = (float) $targetBalance->quantity_on_hand + $returnedWeightKg;
            $targetBalance->save();

            // Record double-entry movement
            InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => 'HOPPER_RETURN',
                'item_id' => $lot->item_id,
                'material_lot_id' => $lot->id,
                'source_bin_id' => $hopperBin->id,
                'destination_bin_id' => $targetBin->id,
                'quantity' => $returnedWeightKg,
                'uom' => $lot->item->unit_of_measure ?? 'KG',
                'reference_type' => 'HOPPER_RETURN',
                'reference_id' => $transaction->transaction_code,
                'user_id' => $userId,
                'notes' => "Partial return of {$returnedWeightKg} kg from machine [{$transaction->machine_code}] to bin [{$targetBin->bin_code}]",
            ]);

            // Update HopperTransaction
            $newReturnedTotal = (float) $transaction->quantity_returned_kg + $returnedWeightKg;
            $transaction->quantity_returned_kg = $newReturnedTotal;
            $transaction->status = HopperTransaction::STATUS_CLOSED;
            $transaction->closed_at = now();
            $transaction->save();

            // Generate restickered return barcode label
            $returnBarcode = "{$lot->lot_number}-RET-".date('His');

            Log::info("HOPPER RETURN COMPLETED [{$transaction->transaction_code}]: Returned {$returnedWeightKg} kg to [{$targetBin->bin_code}]. Label: {$returnBarcode}");

            return [
                'success' => true,
                'transaction_code' => $transaction->transaction_code,
                'returned_weight_kg' => $returnedWeightKg,
                'target_bin' => $targetBin->bin_code,
                'return_barcode' => $returnBarcode,
                'lot_number' => $lot->lot_number,
                'material_code' => $lot->item->sku,
                'status' => 'CLOSED',
            ];
        });
    }
}

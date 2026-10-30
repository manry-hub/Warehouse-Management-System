<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\User;
use App\Models\WarehouseBin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryMovementService
{
    /**
     * Record an inbound material receipt (Dock -> Bin).
     */
    public function recordReceipt(
        Item $item,
        MaterialLot $lot,
        WarehouseBin $destinationBin,
        float $quantity,
        ?string $referenceId = null,
        ?User $user = null,
        ?string $notes = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Receipt quantity must be positive.');
        }

        return DB::transaction(function () use ($item, $lot, $destinationBin, $quantity, $referenceId, $user, $notes) {
            // Update or create destination balance with lock
            $balance = InventoryBalance::where('bin_id', $destinationBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = new InventoryBalance([
                    'bin_id' => $destinationBin->id,
                    'item_id' => $item->id,
                    'material_lot_id' => $lot->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]);
            }

            $balance->quantity_on_hand = (float) $balance->quantity_on_hand + $quantity;
            $balance->save();

            return InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => InventoryMovement::TYPE_RECEIPT,
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'source_bin_id' => null,
                'destination_bin_id' => $destinationBin->id,
                'quantity' => $quantity,
                'uom' => $item->unit_of_measure,
                'reference_type' => 'PURCHASE_ORDER',
                'reference_id' => $referenceId,
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Record internal putaway or bin-to-bin transfer.
     */
    public function recordPutaway(
        MaterialLot $lot,
        WarehouseBin $sourceBin,
        WarehouseBin $destinationBin,
        float $quantity,
        ?User $user = null,
        ?string $notes = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Putaway quantity must be positive.');
        }

        return DB::transaction(function () use ($lot, $sourceBin, $destinationBin, $quantity, $user, $notes) {
            $item = $lot->item;

            // Lock source balance
            $sourceBalance = InventoryBalance::where('bin_id', $sourceBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            $availableQty = $sourceBalance ? ($sourceBalance->quantity_on_hand - $sourceBalance->quantity_reserved) : 0;
            if (! $sourceBalance || $availableQty < $quantity) {
                throw new InsufficientStockException(
                    "Insufficient stock in bin {$sourceBin->bin_code}. Requested: {$quantity}, Available: {$availableQty}"
                );
            }

            // Decrement source
            $sourceBalance->quantity_on_hand = (float) $sourceBalance->quantity_on_hand - $quantity;
            $sourceBalance->save();

            // Lock or create destination
            $destBalance = InventoryBalance::where('bin_id', $destinationBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $destBalance) {
                $destBalance = new InventoryBalance([
                    'bin_id' => $destinationBin->id,
                    'item_id' => $item->id,
                    'material_lot_id' => $lot->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]);
            }

            $destBalance->quantity_on_hand = (float) $destBalance->quantity_on_hand + $quantity;
            $destBalance->save();

            return InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => InventoryMovement::TYPE_PUTAWAY,
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'source_bin_id' => $sourceBin->id,
                'destination_bin_id' => $destinationBin->id,
                'quantity' => $quantity,
                'uom' => $item->unit_of_measure,
                'reference_type' => 'INTERNAL_TRANSFER',
                'reference_id' => null,
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Record staging issue to machine hopper buffer.
     */
    public function recordStagingIssue(
        MaterialLot $lot,
        WarehouseBin $sourceBin,
        WarehouseBin $stagingBin,
        float $quantity,
        string $mesOrderId,
        ?User $user = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Issue quantity must be positive.');
        }

        return DB::transaction(function () use ($lot, $sourceBin, $stagingBin, $quantity, $mesOrderId, $user) {
            $item = $lot->item;

            $sourceBalance = InventoryBalance::where('bin_id', $sourceBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $sourceBalance || $sourceBalance->quantity_on_hand < $quantity) {
                throw new InsufficientStockException('Insufficient stock in source bin for staging issue.');
            }

            // Relieve reservation if present, then deduct on hand
            $reservedDeduction = min($sourceBalance->quantity_reserved, $quantity);
            $sourceBalance->quantity_reserved = (float) $sourceBalance->quantity_reserved - $reservedDeduction;
            $sourceBalance->quantity_on_hand = (float) $sourceBalance->quantity_on_hand - $quantity;
            $sourceBalance->save();

            // Destination staging buffer
            $destBalance = InventoryBalance::where('bin_id', $stagingBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $destBalance) {
                $destBalance = new InventoryBalance([
                    'bin_id' => $stagingBin->id,
                    'item_id' => $item->id,
                    'material_lot_id' => $lot->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]);
            }

            $destBalance->quantity_on_hand = (float) $destBalance->quantity_on_hand + $quantity;
            $destBalance->save();

            return InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => InventoryMovement::TYPE_STAGING_ISSUE,
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'source_bin_id' => $sourceBin->id,
                'destination_bin_id' => $stagingBin->id,
                'quantity' => $quantity,
                'uom' => $item->unit_of_measure,
                'reference_type' => 'MES_PRODUCTION_ORDER',
                'reference_id' => $mesOrderId,
                'user_id' => $user?->id,
                'notes' => "Staged for MES production order {$mesOrderId}",
            ]);
        });
    }

    /**
     * Record finished goods intake from packaging station.
     */
    public function recordFinishedGoodsIntake(
        Item $item,
        MaterialLot $lot,
        WarehouseBin $fgBin,
        float $quantity,
        string $cartonBarcode,
        ?string $mesEventId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $lot, $fgBin, $quantity, $cartonBarcode, $mesEventId) {
            $balance = InventoryBalance::where('bin_id', $fgBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = new InventoryBalance([
                    'bin_id' => $fgBin->id,
                    'item_id' => $item->id,
                    'material_lot_id' => $lot->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]);
            }

            $balance->quantity_on_hand = (float) $balance->quantity_on_hand + $quantity;
            $balance->save();

            return InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => InventoryMovement::TYPE_FG_INTAKE,
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'source_bin_id' => null,
                'destination_bin_id' => $fgBin->id,
                'quantity' => $quantity,
                'uom' => $item->unit_of_measure,
                'reference_type' => 'MES_PACKAGING_EVENT',
                'reference_id' => $mesEventId ?? $cartonBarcode,
                'notes' => "Carton Barcode: {$cartonBarcode}",
            ]);
        });
    }

    /**
     * Record outbound shipment (Bin -> Customer).
     */
    public function recordShipment(
        MaterialLot $lot,
        WarehouseBin $sourceBin,
        float $quantity,
        string $doNumber,
        ?User $user = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Shipment quantity must be positive.');
        }

        return DB::transaction(function () use ($lot, $sourceBin, $quantity, $doNumber, $user) {
            $item = $lot->item;

            $sourceBalance = InventoryBalance::where('bin_id', $sourceBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $sourceBalance || $sourceBalance->quantity_on_hand < $quantity) {
                throw new InsufficientStockException("Insufficient stock in bin {$sourceBin->bin_code} for shipment.");
            }

            $sourceBalance->quantity_on_hand = (float) $sourceBalance->quantity_on_hand - $quantity;
            $sourceBalance->save();

            return InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => InventoryMovement::TYPE_SHIPMENT,
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'source_bin_id' => $sourceBin->id,
                'destination_bin_id' => null,
                'quantity' => $quantity,
                'uom' => $item->unit_of_measure,
                'reference_type' => 'DELIVERY_ORDER',
                'reference_id' => $doNumber,
                'user_id' => $user?->id,
                'notes' => "Dispatched via Delivery Order {$doNumber}",
            ]);
        });
    }
}

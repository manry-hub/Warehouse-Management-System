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
     * Record batch pick movement from storage bin to cart/staging bin.
     */
    public function recordBatchPick(
        MaterialLot $lot,
        WarehouseBin $sourceBin,
        WarehouseBin $destinationBin,
        float $quantity,
        string $batchNumber,
        string $toteIdentifier,
        ?User $user = null,
        ?string $notes = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Pick quantity must be positive.');
        }

        return DB::transaction(function () use ($lot, $sourceBin, $destinationBin, $quantity, $batchNumber, $toteIdentifier, $user, $notes) {
            $item = $lot->item;

            $sourceBalance = InventoryBalance::where('bin_id', $sourceBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $sourceBalance || $sourceBalance->quantity_on_hand < $quantity) {
                throw new InsufficientStockException("Insufficient stock in bin {$sourceBin->bin_code} for batch pick.");
            }

            // Relieve reservation if present, then deduct on hand
            $reservedDeduction = min($sourceBalance->quantity_reserved, $quantity);
            $sourceBalance->quantity_reserved = (float) $sourceBalance->quantity_reserved - $reservedDeduction;
            $sourceBalance->quantity_on_hand = (float) $sourceBalance->quantity_on_hand - $quantity;
            $sourceBalance->save();

            // Destination staging / cart buffer
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
                'movement_type' => InventoryMovement::TYPE_BATCH_PICK,
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'source_bin_id' => $sourceBin->id,
                'destination_bin_id' => $destinationBin->id,
                'quantity' => $quantity,
                'uom' => $item->unit_of_measure,
                'reference_type' => 'PICKING_BATCH',
                'reference_id' => $batchNumber,
                'user_id' => $user?->id,
                'notes' => $notes ?? "Batch {$batchNumber} deposited to {$toteIdentifier}",
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

    /**
     * Record an inventory cycle count adjustment.
     * Positive variance: stock surplus (null -> destinationBin).
     * Negative variance: stock shortage/shrinkage (sourceBin -> null).
     * Zero variance: no movement recorded, returns null.
     */
    public function recordAdjustment(
        WarehouseBin $bin,
        Item $item,
        MaterialLot $lot,
        float $varianceQuantity,
        string $referenceId,
        ?User $user = null,
        ?string $notes = null
    ): ?InventoryMovement {
        if (abs($varianceQuantity) < 0.0001) {
            return null;
        }

        return DB::transaction(function () use ($bin, $item, $lot, $varianceQuantity, $referenceId, $user, $notes) {
            if ($varianceQuantity > 0) {
                // Surplus: add inventory to bin
                $balance = InventoryBalance::where('bin_id', $bin->id)
                    ->where('item_id', $item->id)
                    ->where('material_lot_id', $lot->id)
                    ->lockForUpdate()
                    ->first();

                if (! $balance) {
                    $balance = new InventoryBalance([
                        'bin_id' => $bin->id,
                        'item_id' => $item->id,
                        'material_lot_id' => $lot->id,
                        'quantity_on_hand' => 0,
                        'quantity_reserved' => 0,
                    ]);
                }

                $balance->quantity_on_hand = (float) $balance->quantity_on_hand + $varianceQuantity;
                $balance->save();

                return InventoryMovement::create([
                    'transaction_uuid' => (string) Str::uuid(),
                    'movement_type' => InventoryMovement::TYPE_ADJUSTMENT,
                    'item_id' => $item->id,
                    'material_lot_id' => $lot->id,
                    'source_bin_id' => null,
                    'destination_bin_id' => $bin->id,
                    'quantity' => $varianceQuantity,
                    'uom' => $item->unit_of_measure,
                    'reference_type' => 'CYCLE_COUNT_ORDER',
                    'reference_id' => $referenceId,
                    'user_id' => $user?->id,
                    'notes' => $notes ?? sprintf('Inventory surplus adjustment (+%.3f %s)', $varianceQuantity, $item->unit_of_measure),
                ]);
            } else {
                // Shortage: deduct inventory from bin
                $decrementQty = abs($varianceQuantity);

                $balance = InventoryBalance::where('bin_id', $bin->id)
                    ->where('item_id', $item->id)
                    ->where('material_lot_id', $lot->id)
                    ->lockForUpdate()
                    ->first();

                if (! $balance || (float) $balance->quantity_on_hand < $decrementQty) {
                    $current = $balance ? (float) $balance->quantity_on_hand : 0;
                    throw new InsufficientStockException(
                        "Cannot record negative adjustment: bin {$bin->bin_code} has {$current} {$item->unit_of_measure}, needed {$decrementQty}."
                    );
                }

                $balance->quantity_on_hand = (float) $balance->quantity_on_hand - $decrementQty;
                $balance->save();

                return InventoryMovement::create([
                    'transaction_uuid' => (string) Str::uuid(),
                    'movement_type' => InventoryMovement::TYPE_ADJUSTMENT,
                    'item_id' => $item->id,
                    'material_lot_id' => $lot->id,
                    'source_bin_id' => $bin->id,
                    'destination_bin_id' => null,
                    'quantity' => $decrementQty,
                    'uom' => $item->unit_of_measure,
                    'reference_type' => 'CYCLE_COUNT_ORDER',
                    'reference_id' => $referenceId,
                    'user_id' => $user?->id,
                    'notes' => $notes ?? sprintf('Inventory shortage adjustment (-%.3f %s)', $decrementQty, $item->unit_of_measure),
                ]);
            }
        });
    }

    /**
     * Record an inter-warehouse transfer departure (Origin Bin -> Transit Bin).
     */
    public function recordTransferOut(
        MaterialLot $lot,
        WarehouseBin $originBin,
        WarehouseBin $transitBin,
        float $quantity,
        string $orderNumber,
        ?User $user = null,
        ?string $notes = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Transfer dispatch quantity must be positive.');
        }

        return DB::transaction(function () use ($lot, $originBin, $transitBin, $quantity, $orderNumber, $user, $notes) {
            $item = $lot->item;

            // 1. Lock and deduct from origin bin
            $originBalance = InventoryBalance::where('bin_id', $originBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            $availableQty = $originBalance ? ($originBalance->quantity_on_hand - $originBalance->quantity_reserved) : 0;
            if (! $originBalance || $availableQty < $quantity) {
                throw new InsufficientStockException(
                    "Insufficient stock in origin bin {$originBin->bin_code} for transfer {$orderNumber}. Requested: {$quantity}, Available: {$availableQty}"
                );
            }

            $originBalance->quantity_on_hand = (float) $originBalance->quantity_on_hand - $quantity;
            $originBalance->save();

            // 2. Lock or create transit balance
            $transitBalance = InventoryBalance::where('bin_id', $transitBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if (! $transitBalance) {
                $transitBalance = new InventoryBalance([
                    'bin_id' => $transitBin->id,
                    'item_id' => $item->id,
                    'material_lot_id' => $lot->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]);
            }

            $transitBalance->quantity_on_hand = (float) $transitBalance->quantity_on_hand + $quantity;
            $transitBalance->save();

            // 3. Post movement
            return InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => InventoryMovement::TYPE_TRANSFER_OUT,
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'source_bin_id' => $originBin->id,
                'destination_bin_id' => $transitBin->id,
                'quantity' => $quantity,
                'uom' => $item->unit_of_measure,
                'reference_type' => 'TRANSFER_ORDER',
                'reference_id' => $orderNumber,
                'user_id' => $user?->id,
                'notes' => $notes ?? "Inter-warehouse transfer dispatch: {$orderNumber}",
            ]);
        });
    }

    /**
     * Record an inter-warehouse transfer arrival (Transit Bin -> Destination Bin / Cross-Dock Staging).
     */
    public function recordTransferIn(
        MaterialLot $lot,
        WarehouseBin $transitBin,
        WarehouseBin $destinationBin,
        float $quantity,
        string $orderNumber,
        ?User $user = null,
        ?string $notes = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Transfer receipt quantity must be positive.');
        }

        return DB::transaction(function () use ($lot, $transitBin, $destinationBin, $quantity, $orderNumber, $user, $notes) {
            $item = $lot->item;

            // 1. Lock and deduct from transit bin
            $transitBalance = InventoryBalance::where('bin_id', $transitBin->id)
                ->where('item_id', $item->id)
                ->where('material_lot_id', $lot->id)
                ->lockForUpdate()
                ->first();

            $availableTransit = $transitBalance ? (float) $transitBalance->quantity_on_hand : 0;
            if (! $transitBalance || $availableTransit < $quantity) {
                throw new InsufficientStockException(
                    "Insufficient in-transit inventory for transfer {$orderNumber}. In transit: {$availableTransit}, Requested: {$quantity}"
                );
            }

            $transitBalance->quantity_on_hand = (float) $transitBalance->quantity_on_hand - $quantity;
            $transitBalance->save();

            // 2. Lock or create destination balance
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

            // 3. Post movement
            return InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => InventoryMovement::TYPE_TRANSFER_IN,
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'source_bin_id' => $transitBin->id,
                'destination_bin_id' => $destinationBin->id,
                'quantity' => $quantity,
                'uom' => $item->unit_of_measure,
                'reference_type' => 'TRANSFER_ORDER',
                'reference_id' => $orderNumber,
                'user_id' => $user?->id,
                'notes' => $notes ?? "Inter-warehouse transfer receipt: {$orderNumber}",
            ]);
        });
    }
}

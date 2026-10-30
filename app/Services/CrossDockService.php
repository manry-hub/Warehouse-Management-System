<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\TransferOrderItem;
use App\Models\WarehouseBin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrossDockService
{
    /**
     * Check if an inbound item lot can be cross-docked directly to a pending Delivery Order.
     */
    public function findCrossDockCandidate(Item $item, float $quantity, ?string $destinationWarehouseId = null): ?DeliveryOrder
    {
        $query = DeliveryOrder::whereIn('status', [DeliveryOrder::STATUS_DRAFT, DeliveryOrder::STATUS_PICKING])
            ->whereHas('items', function ($q) use ($item) {
                $q->where('item_id', $item->id)
                    ->where('is_verified_scanned', false);
            })
            ->orderBy('created_at', 'asc');

        return $query->first();
    }

    /**
     * Allocate a transfer item directly to a delivery order and reserve it at the staging dock.
     */
    public function allocateCrossDock(
        TransferOrderItem $transferItem,
        DeliveryOrder $deliveryOrder,
        WarehouseBin $stagingBin
    ): void {
        DB::transaction(function () use ($transferItem, $deliveryOrder, $stagingBin) {
            // 1. Mark transfer line as cross-docked
            $transferItem->update([
                'is_cross_docked' => true,
                'cross_dock_delivery_order_id' => $deliveryOrder->id,
                'destination_bin_id' => $stagingBin->id,
            ]);

            // 2. Link or match with delivery order item line
            $doItem = DeliveryOrderItem::where('delivery_order_id', $deliveryOrder->id)
                ->where('item_id', $transferItem->item_id)
                ->where('is_verified_scanned', false)
                ->first();

            if ($doItem) {
                $doItem->update([
                    'material_lot_id' => $transferItem->material_lot_id,
                    'source_bin_id' => $stagingBin->id,
                    'is_verified_scanned' => true,
                    'quantity_loaded' => $transferItem->quantity,
                ]);
            }

            // 3. Reserve quantity in staging bin balance
            $balance = InventoryBalance::where('bin_id', $stagingBin->id)
                ->where('item_id', $transferItem->item_id)
                ->where('material_lot_id', $transferItem->material_lot_id)
                ->lockForUpdate()
                ->first();

            if ($balance) {
                $balance->quantity_reserved = (float) $balance->quantity_reserved + (float) $transferItem->quantity;
                $balance->save();
            }

            // 4. Update delivery order status if appropriate
            if ($deliveryOrder->status === DeliveryOrder::STATUS_DRAFT) {
                $deliveryOrder->update(['status' => DeliveryOrder::STATUS_PICKING]);
            }

            Log::info("Cross-Dock Bypass: Lot {$transferItem->lot->lot_number} staged directly to {$stagingBin->bin_code} for Delivery Order {$deliveryOrder->do_number}.");
        });
    }
}

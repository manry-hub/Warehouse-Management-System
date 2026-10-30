<?php

namespace App\Services;

use App\Models\TransferOrder;
use App\Models\TransferOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferOrderService
{
    public function __construct(
        protected InventoryMovementService $movementService,
        protected CrossDockService $crossDockService
    ) {}

    /**
     * Create a new draft transfer order with item lines.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{item_id: string, material_lot_id: string, origin_bin_id: string, quantity: float}>  $items
     */
    public function createOrder(array $data, array $items, ?User $user = null): TransferOrder
    {
        if ($data['origin_warehouse_id'] === $data['destination_warehouse_id']) {
            throw new InvalidArgumentException('Origin and destination warehouses must be different.');
        }

        return DB::transaction(function () use ($data, $items, $user) {
            $orderNumber = 'XFER-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            $order = TransferOrder::create([
                'order_number' => $orderNumber,
                'origin_warehouse_id' => $data['origin_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'carrier_name' => $data['carrier_name'] ?? null,
                'vehicle_plate_number' => $data['vehicle_plate_number'] ?? null,
                'waybill_number' => $data['waybill_number'] ?? null,
                'status' => TransferOrder::STATUS_DRAFT,
                'created_by' => $user?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $itemData) {
                TransferOrderItem::create([
                    'transfer_order_id' => $order->id,
                    'item_id' => $itemData['item_id'],
                    'material_lot_id' => $itemData['material_lot_id'],
                    'origin_bin_id' => $itemData['origin_bin_id'],
                    'quantity' => $itemData['quantity'],
                ]);
            }

            return $order;
        });
    }

    /**
     * Approve a draft transfer order for picking and loading.
     */
    public function approveOrder(TransferOrder $order): void
    {
        if ($order->status !== TransferOrder::STATUS_DRAFT) {
            throw new InvalidArgumentException("Cannot approve transfer order with status [{$order->status}].");
        }

        $order->update(['status' => TransferOrder::STATUS_APPROVED]);
    }

    /**
     * Dispatch truck: atomically move inventory from Origin Bins into virtual BIN-TRANSIT.
     */
    public function dispatchOrder(
        TransferOrder $order,
        string $carrierName,
        string $plateNumber,
        string $waybillNumber,
        ?User $user = null
    ): void {
        if ($order->status !== TransferOrder::STATUS_APPROVED) {
            throw new InvalidArgumentException("Cannot dispatch transfer order with status [{$order->status}]. Must be APPROVED.");
        }

        $transitBin = WarehouseBin::where('bin_code', 'BIN-TRANSIT')->firstOrFail();

        DB::transaction(function () use ($order, $carrierName, $plateNumber, $waybillNumber, $transitBin, $user) {
            $order->load('items.lot.item', 'items.originBin');

            foreach ($order->items as $item) {
                $this->movementService->recordTransferOut(
                    lot: $item->lot,
                    originBin: $item->originBin,
                    transitBin: $transitBin,
                    quantity: (float) $item->quantity,
                    orderNumber: $order->order_number,
                    user: $user,
                    notes: "Dispatched via {$carrierName} (Plate: {$plateNumber}, BOL: {$waybillNumber})"
                );
            }

            $order->update([
                'carrier_name' => $carrierName,
                'vehicle_plate_number' => $plateNumber,
                'waybill_number' => $waybillNumber,
                'status' => TransferOrder::STATUS_IN_TRANSIT,
                'dispatched_at' => now(),
            ]);
        });
    }

    /**
     * Receive truck at destination warehouse: move from BIN-TRANSIT to destination rack,
     * or bypass directly to shipping dock if an urgent cross-dock match is detected.
     *
     * @param  array<string, string>  $customBinMappings  Map of item_id => destination_bin_id
     * @return array{order: TransferOrder, cross_docks: array<int, array<string, mixed>>}
     */
    public function receiveOrder(
        TransferOrder $order,
        array $customBinMappings = [],
        ?User $user = null
    ): array {
        if ($order->status !== TransferOrder::STATUS_IN_TRANSIT) {
            throw new InvalidArgumentException("Cannot receive transfer order with status [{$order->status}]. Must be IN_TRANSIT.");
        }

        $transitBin = WarehouseBin::where('bin_code', 'BIN-TRANSIT')->firstOrFail();
        $crossDocks = [];

        return DB::transaction(function () use ($order, $transitBin, $customBinMappings, $user, &$crossDocks) {
            $order->load('items.lot.item');

            // Find destination warehouse staging dock for cross-docking
            $destStagingBin = WarehouseBin::whereHas('zone', function ($q) use ($order) {
                $q->where('warehouse_id', $order->destination_warehouse_id)
                    ->where('zone_type', WarehouseZone::TYPE_STAGING_BUFFER);
            })->first();

            // Default fallback destination storage bin
            $defaultDestBin = WarehouseBin::whereHas('zone', function ($q) use ($order) {
                $q->where('warehouse_id', $order->destination_warehouse_id)
                    ->where('zone_type', WarehouseZone::TYPE_FINISHED_GOODS);
            })->first() ?? $destStagingBin;

            foreach ($order->items as $item) {
                $crossDockCandidate = $this->crossDockService->findCrossDockCandidate(
                    item: $item->lot->item,
                    quantity: (float) $item->quantity,
                    destinationWarehouseId: $order->destination_warehouse_id
                );

                if ($crossDockCandidate && $destStagingBin) {
                    // 1. Cross-dock bypass directly to staging bay!
                    $this->movementService->recordTransferIn(
                        lot: $item->lot,
                        transitBin: $transitBin,
                        destinationBin: $destStagingBin,
                        quantity: (float) $item->quantity,
                        orderNumber: $order->order_number,
                        user: $user,
                        notes: "Cross-dock bypass: directly staged for Delivery Order {$crossDockCandidate->do_number}"
                    );

                    $this->crossDockService->allocateCrossDock($item, $crossDockCandidate, $destStagingBin);

                    $crossDocks[] = [
                        'item_sku' => $item->lot->item->sku,
                        'lot_number' => $item->lot->lot_number,
                        'quantity' => (float) $item->quantity,
                        'delivery_order' => $crossDockCandidate->do_number,
                        'staging_bin' => $destStagingBin->bin_code,
                    ];
                } else {
                    // 2. Standard rack putaway
                    $targetBinId = $customBinMappings[$item->item_id] ?? $defaultDestBin?->id;
                    $targetBin = $targetBinId ? WarehouseBin::findOrFail($targetBinId) : $destStagingBin;

                    $this->movementService->recordTransferIn(
                        lot: $item->lot,
                        transitBin: $transitBin,
                        destinationBin: $targetBin,
                        quantity: (float) $item->quantity,
                        orderNumber: $order->order_number,
                        user: $user,
                        notes: "Transfer received and putaway to {$targetBin->bin_code}"
                    );

                    $item->update(['destination_bin_id' => $targetBin->id]);
                }
            }

            $order->update([
                'status' => TransferOrder::STATUS_COMPLETED,
                'received_at' => now(),
            ]);

            return [
                'order' => $order,
                'cross_docks' => $crossDocks,
            ];
        });
    }
}

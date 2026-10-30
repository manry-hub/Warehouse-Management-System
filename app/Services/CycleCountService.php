<?php

namespace App\Services;

use App\Models\CycleCountItem;
use App\Models\CycleCountOrder;
use App\Models\InventoryBalance;
use App\Models\MaterialLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseZone;
use DomainException;
use Illuminate\Support\Facades\DB;

class CycleCountService
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {}

    /**
     * Create a new Cycle Count Order in DRAFT status.
     */
    public function createOrder(
        Warehouse $warehouse,
        ?WarehouseZone $zone = null,
        string $countType = CycleCountOrder::TYPE_BLIND,
        ?User $assignedTo = null,
        ?string $notes = null
    ): CycleCountOrder {
        $countNumber = $this->generateCountNumber();

        return CycleCountOrder::create([
            'count_number' => $countNumber,
            'warehouse_id' => $warehouse->id,
            'zone_id' => $zone?->id,
            'count_type' => $countType,
            'status' => CycleCountOrder::STATUS_DRAFT,
            'assigned_to' => $assignedTo?->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Start the count: freeze balance snapshot and transition order to IN_PROGRESS.
     */
    public function startCount(CycleCountOrder $order): CycleCountOrder
    {
        if (! $order->canStart()) {
            throw new DomainException("Cannot start cycle count order in status {$order->status}. Must be DRAFT.");
        }

        return DB::transaction(function () use ($order) {
            // Snapshot current inventory balances for target scope
            $query = InventoryBalance::query()
                ->join('warehouse_bins', 'inventory_balances.bin_id', '=', 'warehouse_bins.id')
                ->where('inventory_balances.quantity_on_hand', '>', 0);

            if ($order->zone_id) {
                $query->where('warehouse_bins.zone_id', $order->zone_id);
            } else {
                $query->join('warehouse_zones', 'warehouse_bins.zone_id', '=', 'warehouse_zones.id')
                    ->where('warehouse_zones.warehouse_id', $order->warehouse_id);
            }

            $balances = $query->select('inventory_balances.*')->get();

            foreach ($balances as $balance) {
                CycleCountItem::create([
                    'cycle_count_order_id' => $order->id,
                    'bin_id' => $balance->bin_id,
                    'item_id' => $balance->item_id,
                    'material_lot_id' => $balance->material_lot_id,
                    'recorded_quantity' => $balance->quantity_on_hand,
                    'counted_quantity' => null,
                    'variance_quantity' => null,
                    'status' => CycleCountItem::STATUS_PENDING,
                ]);
            }

            $order->update([
                'status' => CycleCountOrder::STATUS_IN_PROGRESS,
                'snapshot_at' => now(),
            ]);

            return $order->fresh(['items']);
        });
    }

    /**
     * Record a physical count for an item within an order.
     */
    public function recordCount(
        CycleCountOrder $order,
        string $binId,
        string $lotId,
        float $countedQuantity,
        ?User $user = null,
        ?string $counterNotes = null
    ): CycleCountItem {
        if (! in_array($order->status, [
            CycleCountOrder::STATUS_IN_PROGRESS,
            CycleCountOrder::STATUS_COUNTED,
            CycleCountOrder::STATUS_REVIEW,
        ], true)) {
            throw new DomainException("Cannot record count: order is in status {$order->status}.");
        }

        if ($countedQuantity < 0) {
            throw new \InvalidArgumentException('Counted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($order, $binId, $lotId, $countedQuantity, $counterNotes) {
            $item = CycleCountItem::where('cycle_count_order_id', $order->id)
                ->where('bin_id', $binId)
                ->where('material_lot_id', $lotId)
                ->first();

            if (! $item) {
                // Unexpected stock found in bin during count
                $lot = MaterialLot::findOrFail($lotId);
                $item = new CycleCountItem([
                    'cycle_count_order_id' => $order->id,
                    'bin_id' => $binId,
                    'item_id' => $lot->item_id,
                    'material_lot_id' => $lot->id,
                    'recorded_quantity' => 0,
                ]);
            }

            $variance = round($countedQuantity - (float) $item->recorded_quantity, 3);

            $item->counted_quantity = $countedQuantity;
            $item->variance_quantity = $variance;
            $item->status = CycleCountItem::STATUS_COUNTED;
            $item->counted_at = now();
            if ($counterNotes) {
                $item->counter_notes = $counterNotes;
            }
            $item->save();

            // Check if all items in order have been counted
            $pendingCount = CycleCountItem::where('cycle_count_order_id', $order->id)
                ->whereIn('status', [CycleCountItem::STATUS_PENDING, CycleCountItem::STATUS_RECOUNT_REQUESTED])
                ->count();

            if ($pendingCount === 0) {
                $hasVariance = CycleCountItem::where('cycle_count_order_id', $order->id)
                    ->whereRaw('ABS(variance_quantity) > 0.0001')
                    ->exists();

                $order->update([
                    'status' => $hasVariance ? CycleCountOrder::STATUS_REVIEW : CycleCountOrder::STATUS_COUNTED,
                    'completed_at' => now(),
                ]);
            }

            return $item;
        });
    }

    /**
     * Request recount for a specific item.
     */
    public function requestRecount(CycleCountItem $item, ?string $reason = null): CycleCountItem
    {
        return DB::transaction(function () use ($item, $reason) {
            $item->update([
                'counted_quantity' => null,
                'variance_quantity' => null,
                'status' => CycleCountItem::STATUS_RECOUNT_REQUESTED,
                'counter_notes' => $reason ? "Recount requested: {$reason}" : 'Recount requested.',
            ]);

            $order = $item->order;
            if (in_array($order->status, [CycleCountOrder::STATUS_COUNTED, CycleCountOrder::STATUS_REVIEW], true)) {
                $order->update(['status' => CycleCountOrder::STATUS_IN_PROGRESS]);
            }

            return $item;
        });
    }

    /**
     * Approve a completed count order (Supervisor Gate).
     */
    public function approveOrder(CycleCountOrder $order, User $supervisor): CycleCountOrder
    {
        if (! $order->canApprove()) {
            throw new DomainException("Order in status {$order->status} cannot be approved. Must be COUNTED or REVIEW.");
        }

        $order->update([
            'status' => CycleCountOrder::STATUS_APPROVED,
            'approved_by' => $supervisor->id,
        ]);

        return $order->fresh();
    }

    /**
     * Post double-entry inventory adjustments for approved order.
     */
    public function postAdjustments(CycleCountOrder $order, User $supervisor): array
    {
        if (! $order->canPost()) {
            throw new DomainException("Order must be APPROVED before posting adjustments. Current status: {$order->status}.");
        }

        return DB::transaction(function () use ($order, $supervisor) {
            $items = $order->items()
                ->with(['bin', 'item', 'materialLot'])
                ->get();

            $movementsCount = 0;
            $surplusTotal = 0.0;
            $shortageTotal = 0.0;

            foreach ($items as $item) {
                $variance = (float) $item->variance_quantity;

                if (abs($variance) < 0.0001) {
                    $item->update(['status' => CycleCountItem::STATUS_APPROVED]);

                    continue;
                }

                $movement = $this->movementService->recordAdjustment(
                    bin: $item->bin,
                    item: $item->item,
                    lot: $item->materialLot,
                    varianceQuantity: $variance,
                    referenceId: $order->count_number,
                    user: $supervisor,
                    notes: "Stock Opname Adjustment for {$order->count_number}"
                );

                if ($movement) {
                    $movementsCount++;
                    if ($variance > 0) {
                        $surplusTotal += $variance;
                    } else {
                        $shortageTotal += abs($variance);
                    }
                }

                $item->update(['status' => CycleCountItem::STATUS_APPROVED]);
            }

            $order->update([
                'status' => CycleCountOrder::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            return [
                'order_id' => $order->id,
                'count_number' => $order->count_number,
                'movements_recorded' => $movementsCount,
                'total_surplus' => $surplusTotal,
                'total_shortage' => $shortageTotal,
                'status' => CycleCountOrder::STATUS_POSTED,
            ];
        });
    }

    /**
     * Generate unique sequential count number: CC-YYYYMM-XXXX.
     */
    protected function generateCountNumber(): string
    {
        $prefix = 'CC-'.date('Ym').'-';
        $latest = CycleCountOrder::where('count_number', 'like', "{$prefix}%")
            ->orderBy('count_number', 'desc')
            ->value('count_number');

        if (! $latest) {
            return $prefix.'0001';
        }

        $sequence = (int) substr($latest, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CycleCountOrder;
use App\Models\MaterialLot;
use App\Models\WarehouseBin;
use App\Services\CycleCountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScannerCycleCountController extends Controller
{
    public function __construct(
        protected CycleCountService $cycleCountService
    ) {}

    /**
     * List active cycle count orders available for counting.
     */
    public function activeOrders(Request $request): JsonResponse
    {
        $query = CycleCountOrder::whereIn('status', [
            CycleCountOrder::STATUS_IN_PROGRESS,
            CycleCountOrder::STATUS_REVIEW,
        ])
            ->with(['warehouse', 'zone'])
            ->withCount([
                'items as total_items',
                'items as pending_items' => function ($q) {
                    $q->whereIn('status', ['PENDING', 'RECOUNT_REQUESTED']);
                },
            ]);

        if ($request->user()) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('assigned_to')
                    ->orWhere('assigned_to', $request->user()->id);
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Retrieve blind count sheet for a specific order.
     * CRITICAL SECURITY & COMPLIANCE RULE:
     * Does NOT disclose recorded_quantity or variance_quantity to prevent counter confirmation bias.
     */
    public function countSheet(string $id): JsonResponse
    {
        $order = CycleCountOrder::with(['warehouse', 'zone', 'items.bin', 'items.item', 'items.materialLot'])
            ->findOrFail($id);

        $blindItems = $order->items->map(function ($item) {
            return [
                'id' => $item->id,
                'bin_code' => $item->bin->bin_code,
                'aisle' => $item->bin->aisle,
                'rack' => $item->bin->rack,
                'shelf' => $item->bin->shelf,
                'item_sku' => $item->item->sku,
                'item_name' => $item->item->name,
                'lot_number' => $item->materialLot->lot_number,
                'uom' => $item->item->unit_of_measure,
                'status' => $item->status,
                'counted_quantity' => $item->counted_quantity,
                // recorded_quantity and variance_quantity are deliberately omitted
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'count_number' => $order->count_number,
                'warehouse' => $order->warehouse->name,
                'zone' => $order->zone?->name,
                'count_type' => $order->count_type,
                'status' => $order->status,
                'snapshot_at' => $order->snapshot_at?->toIso8601String(),
                'items' => $blindItems,
            ],
        ]);
    }

    /**
     * Record a physical count submission via barcode scan.
     */
    public function recordCount(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'bin_code' => 'required|string|exists:warehouse_bins,bin_code',
            'lot_number' => 'required|string|exists:material_lots,lot_number',
            'counted_quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $order = CycleCountOrder::findOrFail($id);

        $bin = WarehouseBin::where('bin_code', $validated['bin_code'])->firstOrFail();
        $lot = MaterialLot::where('lot_number', $validated['lot_number'])->firstOrFail();

        $item = $this->cycleCountService->recordCount(
            order: $order,
            binId: $bin->id,
            lotId: $lot->id,
            countedQuantity: (float) $validated['counted_quantity'],
            user: $request->user(),
            counterNotes: $validated['notes'] ?? null
        );

        $order->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Physical count recorded successfully.',
            'data' => [
                'item_id' => $item->id,
                'bin_code' => $bin->bin_code,
                'lot_number' => $lot->lot_number,
                'counted_quantity' => (float) $item->counted_quantity,
                'item_status' => $item->status,
                'order_status' => $order->status,
            ],
        ]);
    }
}

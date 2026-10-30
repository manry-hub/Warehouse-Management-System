<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PickingBatch;
use App\Models\PickingBatchItem;
use App\Services\BatchPickingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScannerBatchPickingController extends Controller
{
    public function __construct(
        protected BatchPickingService $batchService
    ) {}

    /**
     * Get active picking batches ready for mobile execution.
     */
    public function activeBatches(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $batches = PickingBatch::whereIn('status', [PickingBatch::STATUS_ASSIGNED, PickingBatch::STATUS_IN_PROGRESS])
            ->where(function ($q) use ($userId) {
                if ($userId) {
                    $q->where('assigned_to', $userId)->orWhereNull('assigned_to');
                }
            })
            ->with(['pickingWave', 'items.bin', 'items.item'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'batches' => $batches->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'wave_number' => $batch->pickingWave->wave_number ?? 'N/A',
                    'cart_code' => $batch->cart_code ?? 'CART-01',
                    'status' => $batch->status,
                    'total_picks' => $batch->total_picks,
                    'completed_picks' => $batch->completed_picks,
                    'progress_pct' => $batch->total_picks > 0 ? round(($batch->completed_picks / $batch->total_picks) * 100) : 0,
                ];
            }),
        ]);
    }

    /**
     * Get ordered pick items and current target task for a specific batch.
     */
    public function batchTasks(Request $request, string $id): JsonResponse
    {
        $batch = PickingBatch::with([
            'pickingWave',
            'items' => fn ($q) => $q->orderBy('pick_sequence'),
            'items.bin',
            'items.item',
            'items.materialLot',
        ])->findOrFail($id);

        $currentItem = $batch->items->where('status', PickingBatchItem::STATUS_PENDING)->first();

        return response()->json([
            'success' => true,
            'batch' => [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'wave_number' => $batch->pickingWave->wave_number ?? 'N/A',
                'cart_code' => $batch->cart_code,
                'status' => $batch->status,
                'total_picks' => $batch->total_picks,
                'completed_picks' => $batch->completed_picks,
            ],
            'current_task' => $currentItem ? [
                'id' => $currentItem->id,
                'pick_sequence' => $currentItem->pick_sequence,
                'bin_code' => $currentItem->bin->bin_code ?? '',
                'aisle' => $currentItem->bin->aisle ?? '',
                'rack' => $currentItem->bin->rack ?? '',
                'shelf' => $currentItem->bin->shelf ?? '',
                'lot_number' => $currentItem->materialLot->lot_number ?? '',
                'item_sku' => $currentItem->item->item_code ?? '',
                'item_name' => $currentItem->item->name ?? '',
                'quantity_to_pick' => (float) $currentItem->quantity_to_pick,
                'uom' => $currentItem->item->unit_of_measure ?? 'KG',
                'target_tote' => $currentItem->target_tote,
            ] : null,
            'all_tasks' => $batch->items->map(fn ($item) => [
                'id' => $item->id,
                'pick_sequence' => $item->pick_sequence,
                'bin_code' => $item->bin->bin_code ?? '',
                'item_sku' => $item->item->item_code ?? '',
                'lot_number' => $item->materialLot->lot_number ?? '',
                'quantity_to_pick' => (float) $item->quantity_to_pick,
                'target_tote' => $item->target_tote,
                'status' => $item->status,
            ]),
        ]);
    }

    /**
     * Validate and confirm a single batch item pick.
     */
    public function confirmItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_item_id' => 'required|uuid|exists:picking_batch_items,id',
            'scanned_bin' => 'required|string',
            'scanned_lot' => 'required|string',
            'scanned_tote' => 'required|string',
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        $batchItem = PickingBatchItem::with(['bin', 'materialLot.item', 'batch.pickingWave'])->findOrFail($validated['batch_item_id']);

        try {
            $result = $this->batchService->confirmItemPick(
                batchItem: $batchItem,
                scannedBinCode: $validated['scanned_bin'],
                scannedLotNumber: $validated['scanned_lot'],
                scannedToteCode: $validated['scanned_tote'],
                quantityPicked: (float) $validated['quantity'],
                user: $request->user()
            );

            return response()->json(array_merge(['success' => true], $result));
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Pick validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

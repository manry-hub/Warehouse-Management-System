<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReplenishmentTask;
use App\Services\ReplenishmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScannerReplenishmentController extends Controller
{
    public function __construct(
        protected ReplenishmentService $replenishmentService
    ) {}

    /**
     * Get prioritized replenishment missions for mobile forklift terminals.
     */
    public function pending(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $tasks = ReplenishmentTask::whereIn('status', [
            ReplenishmentTask::STATUS_PENDING,
            ReplenishmentTask::STATUS_ASSIGNED,
            ReplenishmentTask::STATUS_IN_TRANSIT,
        ])
            ->where(function ($q) use ($userId) {
                if ($userId) {
                    $q->where('assigned_to', $userId)->orWhereNull('assigned_to');
                }
            })
            ->with(['targetBin', 'sourceBin', 'item', 'materialLot'])
            ->orderByRaw("CASE priority WHEN 'CRITICAL' THEN 1 WHEN 'URGENT' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'tasks' => $tasks->map(fn ($task) => [
                'id' => $task->id,
                'task_number' => $task->task_number,
                'priority' => $task->priority,
                'status' => $task->status,
                'target_bin' => $task->targetBin->bin_code ?? 'N/A',
                'source_bin' => $task->sourceBin->bin_code ?? 'N/A',
                'item_sku' => $task->item->item_code ?? 'N/A',
                'item_name' => $task->item->name ?? 'N/A',
                'lot_number' => $task->materialLot->lot_number ?? 'N/A',
                'quantity_kg' => (float) $task->requested_quantity_kg,
            ]),
        ]);
    }

    /**
     * Mark task in-transit by driver.
     */
    public function start(Request $request, string $id): JsonResponse
    {
        $task = ReplenishmentTask::findOrFail($id);

        if ($task->status === ReplenishmentTask::STATUS_PENDING) {
            $task->status = ReplenishmentTask::STATUS_IN_TRANSIT;
            $task->assigned_to = $request->user()?->id;
            $task->started_at = now();
            $task->save();
        }

        return response()->json([
            'success' => true,
            'task' => $task,
        ]);
    }

    /**
     * Confirm physical replenishment with source bin, lot barcode, and target bin scans.
     */
    public function confirm(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'scanned_source_bin' => 'required|string',
            'scanned_lot_number' => 'required|string',
            'scanned_target_bin' => 'required|string',
            'quantity_kg' => 'required|numeric|min:0.01',
        ]);

        $task = ReplenishmentTask::with(['targetBin', 'sourceBin', 'materialLot.item'])->findOrFail($id);

        try {
            $result = $this->replenishmentService->confirmReplenishment(
                task: $task,
                scannedSourceBin: $validated['scanned_source_bin'],
                scannedLotNumber: $validated['scanned_lot_number'],
                scannedTargetBin: $validated['scanned_target_bin'],
                quantity: (float) $validated['quantity_kg'],
                driver: $request->user()
            );

            return response()->json(array_merge(['success' => true], $result));
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
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

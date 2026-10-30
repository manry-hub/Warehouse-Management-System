<?php

namespace App\Services;

use App\Models\PickingBatch;
use App\Models\PickingBatchItem;
use App\Models\PickingWave;
use App\Models\PickingWaveOrder;
use App\Models\User;
use App\Models\WarehouseBin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchPickingService
{
    public function __construct(
        protected PickPathRoutingService $routingService,
        protected InventoryMovementService $movementService
    ) {}

    /**
     * Partition wave orders into multi-tote batches and sort batch pick sequences using S-Path routing.
     *
     * @return Collection<int, PickingBatch>
     */
    public function generateBatchesForWave(PickingWave $wave, int $maxOrdersPerBatch = 4): Collection
    {
        return DB::transaction(function () use ($wave, $maxOrdersPerBatch) {
            $waveOrders = $wave->waveOrders()->with('orderable.items')->get();
            $batches = collect();

            if ($waveOrders->isEmpty()) {
                return $batches;
            }

            // Group wave orders into chunks up to maxOrdersPerBatch
            $orderChunks = $waveOrders->chunk($maxOrdersPerBatch);

            foreach ($orderChunks as $chunkIndex => $chunk) {
                $batchNumber = sprintf('BAT-%s-%03d', now()->format('Ymd'), $chunkIndex + 1);

                $batch = PickingBatch::create([
                    'batch_number' => $batchNumber,
                    'picking_wave_id' => $wave->id,
                    'status' => PickingBatch::STATUS_ASSIGNED,
                    'cart_code' => sprintf('CART-%02d', $chunkIndex + 1),
                    'total_picks' => 0,
                    'completed_picks' => 0,
                ]);

                $candidateItems = collect();

                foreach ($chunk->values() as $orderIndex => $waveOrder) {
                    $toteIdentifier = sprintf('TOTE-%02d', $orderIndex + 1);
                    $waveOrder->assigned_tote = $toteIdentifier;
                    $waveOrder->status = PickingWaveOrder::STATUS_BATCHED;
                    $waveOrder->save();

                    $order = $waveOrder->orderable;
                    if (! $order) {
                        continue;
                    }

                    // Extract items from DeliveryOrder or StagingRequest
                    foreach ($order->items as $orderItem) {
                        $lotId = $orderItem->material_lot_id;
                        $binId = $orderItem->source_bin_id;
                        $qty = (float) ($orderItem->quantity_ordered ?? $orderItem->quantity_requested ?? 0);

                        if (! $lotId || ! $binId || $qty <= 0) {
                            continue;
                        }

                        $candidateItems->push([
                            'wave_order_id' => $waveOrder->id,
                            'item_id' => $orderItem->item_id,
                            'material_lot_id' => $lotId,
                            'warehouse_bin_id' => $binId,
                            'quantity_to_pick' => $qty,
                            'target_tote' => $toteIdentifier,
                        ]);
                    }
                }

                // Apply S-Shape Serpentine Route Optimization
                $sortedItems = $this->routingService->sortSerpentine($candidateItems);

                $totalPicks = 0;
                foreach ($sortedItems as $index => $itemData) {
                    PickingBatchItem::create([
                        'picking_batch_id' => $batch->id,
                        'picking_wave_order_id' => $itemData['wave_order_id'],
                        'item_id' => $itemData['item_id'],
                        'material_lot_id' => $itemData['material_lot_id'],
                        'warehouse_bin_id' => $itemData['warehouse_bin_id'],
                        'quantity_to_pick' => $itemData['quantity_to_pick'],
                        'quantity_picked' => 0,
                        'target_tote' => $itemData['target_tote'],
                        'pick_sequence' => $itemData['pick_sequence'] ?? ($index + 1),
                        'status' => PickingBatchItem::STATUS_PENDING,
                    ]);
                    $totalPicks++;
                }

                $batch->total_picks = $totalPicks;
                $batch->save();

                $batches->push($batch);
            }

            return $batches;
        });
    }

    /**
     * Confirm a single batch pick line with bin, lot, and tote verification.
     */
    public function confirmItemPick(
        PickingBatchItem $batchItem,
        string $scannedBinCode,
        string $scannedLotNumber,
        string $scannedToteCode,
        float $quantityPicked,
        ?User $user = null
    ): array {
        if ($batchItem->status !== PickingBatchItem::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'This pick task has already been completed or cancelled.',
            ]);
        }

        // 1. Bin Verification
        $sourceBin = $batchItem->bin;
        if (strcasecmp(trim($sourceBin->bin_code), trim($scannedBinCode)) !== 0) {
            throw ValidationException::withMessages([
                'scanned_bin' => "Scanned bin [{$scannedBinCode}] does not match required bin [{$sourceBin->bin_code}].",
            ]);
        }

        // 2. Material Lot Verification
        $lot = $batchItem->materialLot;
        if (strcasecmp(trim($lot->lot_number), trim($scannedLotNumber)) !== 0) {
            throw ValidationException::withMessages([
                'scanned_lot' => "Scanned lot [{$scannedLotNumber}] does not match allocated lot [{$lot->lot_number}].",
            ]);
        }

        // 3. Put-to-Tote Verification
        if (strcasecmp(trim($batchItem->target_tote), trim($scannedToteCode)) !== 0) {
            throw ValidationException::withMessages([
                'scanned_tote' => "Scanned tote [{$scannedToteCode}] does not match target order tote [{$batchItem->target_tote}].",
            ]);
        }

        // 4. Quantity Validation
        if ($quantityPicked <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Picked quantity must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($batchItem, $lot, $sourceBin, $quantityPicked, $user) {
            // Locate staging bin for pick cart drop
            $stagingBin = WarehouseBin::where('bin_code', 'LIKE', '%STG%')
                ->orWhere('bin_code', 'LIKE', '%STAGING%')
                ->first();

            if (! $stagingBin) {
                $stagingBin = $sourceBin; // Fallback in tests
            }

            // Record atomic double-entry movement
            $movement = $this->movementService->recordBatchPick(
                lot: $lot,
                sourceBin: $sourceBin,
                destinationBin: $stagingBin,
                quantity: $quantityPicked,
                batchNumber: $batchItem->batch->batch_number,
                toteIdentifier: $batchItem->target_tote,
                user: $user,
                notes: "Wave {$batchItem->batch->pickingWave->wave_number} pick into {$batchItem->target_tote}"
            );

            // Update Batch Item
            $batchItem->quantity_picked = $quantityPicked;
            $batchItem->status = ($quantityPicked >= (float) $batchItem->quantity_to_pick)
                ? PickingBatchItem::STATUS_PICKED
                : PickingBatchItem::STATUS_SHORT;
            $batchItem->picked_at = now();
            $batchItem->picked_by = $user?->id;
            $batchItem->save();

            // Update Batch Progress
            $batch = $batchItem->batch;
            $batch->completed_picks = $batch->items()->whereIn('status', [PickingBatchItem::STATUS_PICKED, PickingBatchItem::STATUS_SHORT])->count();
            if ($batch->completed_picks >= $batch->total_picks) {
                $batch->status = PickingBatch::STATUS_COMPLETED;
                $batch->completed_at = now();
            } else {
                $batch->status = PickingBatch::STATUS_IN_PROGRESS;
                if (! $batch->started_at) {
                    $batch->started_at = now();
                }
            }
            $batch->save();

            // Update Wave Progress
            $wave = $batch->pickingWave;
            $wave->picked_lines = PickingBatchItem::whereHas('batch', fn ($q) => $q->where('picking_wave_id', $wave->id))
                ->whereIn('status', [PickingBatchItem::STATUS_PICKED, PickingBatchItem::STATUS_SHORT])
                ->count();

            if ($wave->picked_lines >= $wave->total_lines && $wave->total_lines > 0) {
                $wave->status = PickingWave::STATUS_COMPLETED;
                $wave->completed_at = now();
            } else {
                $wave->status = PickingWave::STATUS_IN_PROGRESS;
            }
            $wave->save();

            // Next pending item in batch sequence
            $nextItem = $batch->items()
                ->where('status', PickingBatchItem::STATUS_PENDING)
                ->orderBy('pick_sequence')
                ->first();

            return [
                'status' => 'success',
                'message' => "Successfully picked {$quantityPicked} {$lot->item->unit_of_measure} into {$batchItem->target_tote}",
                'batch_completed' => $batch->status === PickingBatch::STATUS_COMPLETED,
                'wave_completed' => $wave->status === PickingWave::STATUS_COMPLETED,
                'next_item' => $nextItem ? [
                    'id' => $nextItem->id,
                    'pick_sequence' => $nextItem->pick_sequence,
                    'bin_code' => $nextItem->bin->bin_code,
                    'lot_number' => $nextItem->materialLot->lot_number,
                    'target_tote' => $nextItem->target_tote,
                    'quantity_to_pick' => (float) $nextItem->quantity_to_pick,
                    'item_sku' => $nextItem->item->item_code,
                    'item_name' => $nextItem->item->name,
                ] : null,
            ];
        });
    }
}

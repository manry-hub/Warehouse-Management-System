<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\MaterialLot;
use App\Models\ReplenishmentTask;
use App\Models\User;
use App\Models\WarehouseBin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplenishmentService
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {}

    /**
     * Scan forward-pick bins, evaluate inventory deficits against min/max thresholds,
     * and auto-generate replenishment missions reserving FEFO bulk lots.
     *
     * @return Collection<int, ReplenishmentTask>
     */
    public function evaluateDeficits(?string $zoneId = null): Collection
    {
        return DB::transaction(function () use ($zoneId) {
            $query = WarehouseBin::where('is_pick_face', true)
                ->whereNotNull('min_stock_kg')
                ->where('is_blocked', false);

            if ($zoneId) {
                $query->where('zone_id', $zoneId);
            }

            $pickBins = $query->with('balances.lot.item')->get();
            $spawnedTasks = collect();

            foreach ($pickBins as $bin) {
                $minStock = (float) $bin->min_stock_kg;
                $maxStock = (float) ($bin->max_stock_kg ?? ($minStock * 2.5));

                // Find distinct items assigned/present in this bin
                $itemsInBin = $bin->balances->map(fn ($b) => $b->item)->unique('id')->filter();

                // If bin is completely empty, look up recent historical balance or fallback to first item
                if ($itemsInBin->isEmpty()) {
                    $historicalBalance = InventoryBalance::where('bin_id', $bin->id)->with('item')->first();
                    if ($historicalBalance?->item) {
                        $itemsInBin = collect([$historicalBalance->item]);
                    }
                }

                foreach ($itemsInBin as $item) {
                    // Check duplicate prevention guard
                    $hasOpenTask = ReplenishmentTask::where('target_bin_id', $bin->id)
                        ->where('item_id', $item->id)
                        ->whereIn('status', [
                            ReplenishmentTask::STATUS_PENDING,
                            ReplenishmentTask::STATUS_ASSIGNED,
                            ReplenishmentTask::STATUS_IN_TRANSIT,
                        ])
                        ->exists();

                    if ($hasOpenTask) {
                        continue;
                    }

                    // Compute effective on hand in this pick face bin
                    $currentOnHand = (float) InventoryBalance::where('bin_id', $bin->id)
                        ->where('item_id', $item->id)
                        ->sum('quantity_on_hand');

                    $currentReserved = (float) InventoryBalance::where('bin_id', $bin->id)
                        ->where('item_id', $item->id)
                        ->sum('quantity_reserved');

                    $inTransitReplenishment = (float) ReplenishmentTask::where('target_bin_id', $bin->id)
                        ->where('item_id', $item->id)
                        ->whereIn('status', [
                            ReplenishmentTask::STATUS_PENDING,
                            ReplenishmentTask::STATUS_ASSIGNED,
                            ReplenishmentTask::STATUS_IN_TRANSIT,
                        ])
                        ->sum('requested_quantity_kg');

                    $effectiveStock = max(0.0, ($currentOnHand - $currentReserved) + $inTransitReplenishment);

                    // Check if stock is below minimum safety threshold
                    if ($effectiveStock < $minStock) {
                        $deficit = max(1.0, round($maxStock - $effectiveStock, 3));

                        // Search candidate bulk storage bins for eligible FEFO lots
                        $candidateBalance = InventoryBalance::whereHas('bin', function ($q) {
                            $q->where('is_pick_face', false)
                                ->where('is_blocked', false);
                        })
                            ->where('inventory_balances.item_id', $item->id)
                            ->whereHas('lot', fn ($q) => $q->where('status', MaterialLot::STATUS_AVAILABLE))
                            ->whereRaw('(inventory_balances.quantity_on_hand - inventory_balances.quantity_reserved) > 0')
                            ->join('material_lots', 'inventory_balances.material_lot_id', '=', 'material_lots.id')
                            ->select('inventory_balances.*', 'material_lots.expiry_date as lot_expiry', 'material_lots.created_at as lot_created')
                            ->orderBy('lot_expiry', 'asc')
                            ->orderBy('lot_created', 'asc')
                            ->lockForUpdate()
                            ->first();

                        if (! $candidateBalance) {
                            continue; // No bulk stock available in facility to replenish
                        }

                        $availableInBulk = (float) ($candidateBalance->quantity_on_hand - $candidateBalance->quantity_reserved);
                        $replenishQty = min($deficit, $availableInBulk);

                        if ($replenishQty <= 0) {
                            continue;
                        }

                        // Determine urgency priority
                        $priority = ReplenishmentTask::PRIORITY_NORMAL;
                        if ($effectiveStock <= 0) {
                            $priority = ReplenishmentTask::PRIORITY_CRITICAL;
                        } elseif ($effectiveStock < ($minStock * 0.5)) {
                            $priority = ReplenishmentTask::PRIORITY_URGENT;
                        }

                        // Reserve stock in source bulk bin
                        $candidateBalance->quantity_reserved = (float) $candidateBalance->quantity_reserved + $replenishQty;
                        $candidateBalance->save();

                        // Generate unique task number
                        $taskNumber = sprintf(
                            'RPL-%s-%04d',
                            now()->format('Ymd'),
                            ReplenishmentTask::whereDate('created_at', today())->count() + 1
                        );

                        $task = ReplenishmentTask::create([
                            'task_number' => $taskNumber,
                            'target_bin_id' => $bin->id,
                            'source_bin_id' => $candidateBalance->bin_id,
                            'item_id' => $item->id,
                            'material_lot_id' => $candidateBalance->material_lot_id,
                            'requested_quantity_kg' => $replenishQty,
                            'replenished_quantity_kg' => 0,
                            'status' => ReplenishmentTask::STATUS_PENDING,
                            'priority' => $priority,
                            'notes' => "Auto-triggered deficit. Pick Face: {$effectiveStock} KG < Min {$minStock} KG",
                        ]);

                        $spawnedTasks->push($task);
                    }
                }
            }

            return $spawnedTasks;
        });
    }

    /**
     * Confirm physical replenishment transfer executed by a forklift driver.
     */
    public function confirmReplenishment(
        ReplenishmentTask $task,
        string $scannedSourceBin,
        string $scannedLotNumber,
        string $scannedTargetBin,
        float $quantity,
        ?User $driver = null
    ): array {
        if (in_array($task->status, [ReplenishmentTask::STATUS_COMPLETED, ReplenishmentTask::STATUS_CANCELLED])) {
            throw ValidationException::withMessages([
                'status' => "Task {$task->task_number} is already {$task->status}.",
            ]);
        }

        // 1. Source Bin Verification
        $sourceBin = $task->sourceBin;
        if (! $sourceBin || strcasecmp(trim($sourceBin->bin_code), trim($scannedSourceBin)) !== 0) {
            $expected = $sourceBin->bin_code ?? 'None';
            throw ValidationException::withMessages([
                'scanned_source_bin' => "Scanned source bin [{$scannedSourceBin}] does not match bulk location [{$expected}].",
            ]);
        }

        // 2. Material Lot Verification
        $lot = $task->materialLot;
        if (! $lot || strcasecmp(trim($lot->lot_number), trim($scannedLotNumber)) !== 0) {
            $expectedLot = $lot->lot_number ?? 'None';
            throw ValidationException::withMessages([
                'scanned_lot_number' => "Scanned lot [{$scannedLotNumber}] does not match FEFO reserved lot [{$expectedLot}].",
            ]);
        }

        // 3. Target Pick Face Bin Verification
        $targetBin = $task->targetBin;
        if (strcasecmp(trim($targetBin->bin_code), trim($scannedTargetBin)) !== 0) {
            throw ValidationException::withMessages([
                'scanned_target_bin' => "Scanned target bin [{$scannedTargetBin}] does not match designated pick face [{$targetBin->bin_code}].",
            ]);
        }

        // 4. Quantity Validation
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Replenished quantity must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($task, $lot, $sourceBin, $targetBin, $quantity, $driver) {
            // Execute double-entry movement debiting bulk and crediting pick face
            $movement = $this->movementService->recordReplenishment(
                lot: $lot,
                sourceBulkBin: $sourceBin,
                targetPickBin: $targetBin,
                quantity: $quantity,
                taskNumber: $task->task_number,
                user: $driver,
                notes: "Replenished by {$driver?->name} ({$task->task_number})"
            );

            // Mark task as completed
            $task->replenished_quantity_kg = $quantity;
            $task->status = ReplenishmentTask::STATUS_COMPLETED;
            $task->completed_at = now();
            if ($driver) {
                $task->assigned_to = $driver->id;
            }
            $task->save();

            return [
                'status' => 'success',
                'message' => "Successfully replenished {$quantity} KG into pick face {$targetBin->bin_code}",
                'task_number' => $task->task_number,
                'target_bin' => $targetBin->bin_code,
                'movement_uuid' => $movement->transaction_uuid,
            ];
        });
    }

    /**
     * Cancel replenishment task and relieve bulk reservation.
     */
    public function cancelTask(ReplenishmentTask $task, ?string $reason = null): ReplenishmentTask
    {
        if ($task->status === ReplenishmentTask::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'status' => 'Cannot cancel a completed replenishment task.',
            ]);
        }

        return DB::transaction(function () use ($task, $reason) {
            // Relieve reservation in bulk bin
            if ($task->source_bin_id && $task->material_lot_id) {
                $balance = InventoryBalance::where('bin_id', $task->source_bin_id)
                    ->where('material_lot_id', $task->material_lot_id)
                    ->lockForUpdate()
                    ->first();

                if ($balance) {
                    $reservedToRelease = min($balance->quantity_reserved, (float) $task->requested_quantity_kg);
                    $balance->quantity_reserved = max(0.0, (float) $balance->quantity_reserved - $reservedToRelease);
                    $balance->save();
                }
            }

            $task->status = ReplenishmentTask::STATUS_CANCELLED;
            if ($reason) {
                $task->notes = ($task->notes ? $task->notes."\n" : '')."Cancelled: {$reason}";
            }
            $task->save();

            return $task;
        });
    }
}

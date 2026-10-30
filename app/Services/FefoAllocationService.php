<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\MaterialLot;
use Illuminate\Support\Facades\DB;

class FefoAllocationService
{
    /**
     * Allocate lots for an item based on strict First-Expired, First-Out (FEFO),
     * enforcing the maximum regrind blend cap when applicable.
     * Locks candidate balances atomically to prevent concurrent race conditions.
     *
     * @return array<int, array{balance_id: string, lot_id: string, bin_id: string, lot_number: string, bin_code: string, lot_type: string, is_regrind: bool, blend_percentage: float, expiry_date: ?string, quantity: float}>
     */
    public function allocate(Item $item, float $requiredQuantity, bool $enforceRegrindBlendCap = true): array
    {
        if ($requiredQuantity <= 0) {
            throw new \InvalidArgumentException('Required quantity must be positive.');
        }

        return DB::transaction(function () use ($item, $requiredQuantity, $enforceRegrindBlendCap) {
            $allocations = [];
            $remainingNeeded = $requiredQuantity;

            // Step 1: If regrind is allowed and requested, allocate regrind lots up to the blend cap
            if ($enforceRegrindBlendCap && $item->allowsRegrind()) {
                $maxRegrindPct = (float) $item->max_regrind_percentage;
                $maxRegrindQty = round($requiredQuantity * ($maxRegrindPct / 100.0), 3);

                if ($maxRegrindQty > 0) {
                    $regrindBalances = $this->getCandidateBalances($item, MaterialLot::TYPE_REGRIND);
                    $allocatedRegrind = 0.0;

                    foreach ($regrindBalances as $balance) {
                        $available = (float) ($balance->quantity_on_hand - $balance->quantity_reserved);
                        if ($available <= 0) {
                            continue;
                        }

                        $allowableRegrindRemaining = $maxRegrindQty - $allocatedRegrind;
                        if ($allowableRegrindRemaining <= 0.0001) {
                            break;
                        }

                        $allocateQty = min($available, $allowableRegrindRemaining, $remainingNeeded);
                        if ($allocateQty <= 0) {
                            continue;
                        }

                        // Reserve the allocated amount
                        $balance->quantity_reserved = (float) $balance->quantity_reserved + $allocateQty;
                        $balance->save();

                        $lot = $balance->lot;
                        $bin = $balance->bin;

                        $allocations[] = [
                            'balance_id' => $balance->id,
                            'lot_id' => $lot->id,
                            'bin_id' => $bin->id,
                            'lot_number' => $lot->lot_number,
                            'bin_code' => $bin->bin_code,
                            'lot_type' => $lot->lot_type ?? MaterialLot::TYPE_REGRIND,
                            'is_regrind' => true,
                            'blend_percentage' => round(($allocateQty / $requiredQuantity) * 100, 2),
                            'expiry_date' => $lot->expiry_date?->toDateString(),
                            'quantity' => $allocateQty,
                        ];

                        $allocatedRegrind += $allocateQty;
                        $remainingNeeded -= $allocateQty;

                        if ($remainingNeeded <= 0.0001) {
                            break;
                        }
                    }
                }
            }

            // Step 2: Allocate the remaining required quantity from VIRGIN lots (or any valid lots if blend cap not enforced)
            if ($remainingNeeded > 0.0001) {
                // When blend cap is enforced, any further allocation must strictly be virgin resin (non-regrind)
                $virginBalances = $this->getCandidateBalances(
                    item: $item,
                    lotType: null,
                    excludeRegrind: $enforceRegrindBlendCap
                );

                foreach ($virginBalances as $balance) {
                    $available = (float) ($balance->quantity_on_hand - $balance->quantity_reserved);
                    if ($available <= 0) {
                        continue;
                    }

                    $allocateQty = min($available, $remainingNeeded);

                    // Reserve the allocated amount
                    $balance->quantity_reserved = (float) $balance->quantity_reserved + $allocateQty;
                    $balance->save();

                    $lot = $balance->lot;
                    $bin = $balance->bin;
                    $isRegrind = $lot->isRegrind();

                    $allocations[] = [
                        'balance_id' => $balance->id,
                        'lot_id' => $lot->id,
                        'bin_id' => $bin->id,
                        'lot_number' => $lot->lot_number,
                        'bin_code' => $bin->bin_code,
                        'lot_type' => $lot->lot_type ?? MaterialLot::TYPE_VIRGIN,
                        'is_regrind' => $isRegrind,
                        'blend_percentage' => round(($allocateQty / $requiredQuantity) * 100, 2),
                        'expiry_date' => $lot->expiry_date?->toDateString(),
                        'quantity' => $allocateQty,
                    ];

                    $remainingNeeded -= $allocateQty;

                    if ($remainingNeeded <= 0.0001) {
                        break;
                    }
                }
            }

            if ($remainingNeeded > 0.0001) {
                $allocatedSoFar = $requiredQuantity - $remainingNeeded;
                throw new InsufficientStockException(
                    "Insufficient available stock for SKU [{$item->sku}]. Requested: {$requiredQuantity}, Available: {$allocatedSoFar}"
                );
            }

            return $allocations;
        });
    }

    /**
     * Fetch candidate balances locked for update, ordered by FEFO.
     */
    protected function getCandidateBalances(Item $item, ?string $lotType = null, bool $excludeRegrind = false)
    {
        $query = InventoryBalance::query()
            ->select('inventory_balances.*')
            ->join('material_lots', 'inventory_balances.material_lot_id', '=', 'material_lots.id')
            ->join('warehouse_bins', 'inventory_balances.bin_id', '=', 'warehouse_bins.id')
            ->join('warehouse_zones', 'warehouse_bins.zone_id', '=', 'warehouse_zones.id')
            ->where('inventory_balances.item_id', $item->id)
            ->where('material_lots.status', MaterialLot::STATUS_AVAILABLE)
            ->where(function ($q) {
                $q->whereNull('material_lots.expiry_date')
                    ->orWhere('material_lots.expiry_date', '>=', now()->toDateString());
            })
            ->where('warehouse_bins.is_blocked', false)
            ->where('warehouse_zones.zone_type', '!=', 'QUARANTINE')
            ->whereRaw('(inventory_balances.quantity_on_hand - inventory_balances.quantity_reserved) > 0')
            ->orderBy('material_lots.expiry_date', 'asc')
            ->orderBy('material_lots.created_at', 'asc')
            ->lockForUpdate();

        if ($lotType !== null) {
            $query->where('material_lots.lot_type', $lotType);
        }

        if ($excludeRegrind) {
            $query->where('material_lots.lot_type', '!=', MaterialLot::TYPE_REGRIND);
        }

        return $query->get();
    }

    /**
     * Release previously reserved balances if a picklist is cancelled.
     */
    public function releaseReservation(string $balanceId, float $quantity): void
    {
        DB::transaction(function () use ($balanceId, $quantity) {
            $balance = InventoryBalance::where('id', $balanceId)->lockForUpdate()->first();
            if ($balance) {
                $releaseQty = min((float) $balance->quantity_reserved, $quantity);
                $balance->quantity_reserved = (float) $balance->quantity_reserved - $releaseQty;
                $balance->save();
            }
        });
    }
}

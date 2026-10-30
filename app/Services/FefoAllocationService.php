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
     * Allocate lots for an item based on strict First-Expired, First-Out (FEFO).
     * Locks candidate balances atomically to prevent concurrent race conditions.
     *
     * @return array<int, array{balance_id: string, lot_id: string, bin_id: string, lot_number: string, bin_code: string, expiry_date: ?string, quantity: float}>
     */
    public function allocate(Item $item, float $requiredQuantity): array
    {
        if ($requiredQuantity <= 0) {
            throw new \InvalidArgumentException('Required quantity must be positive.');
        }

        return DB::transaction(function () use ($item, $requiredQuantity) {
            // Find all available balances for this item in active storage zones
            // Candidate lots must be strictly in AVAILABLE status and not expired
            $candidateBalances = InventoryBalance::query()
                ->select('inventory_balances.*')
                ->join('material_lots', 'inventory_balances.material_lot_id', '=', 'material_lots.id')
                ->join('warehouse_bins', 'inventory_balances.bin_id', '=', 'warehouse_bins.id')
                ->join('warehouse_zones', 'warehouse_bins.zone_id', '=', 'warehouse_zones.id')
                ->where('inventory_balances.item_id', $item->id)
                ->where('material_lots.status', MaterialLot::STATUS_AVAILABLE)
                ->where(function ($query) {
                    $query->whereNull('material_lots.expiry_date')
                        ->orWhere('material_lots.expiry_date', '>=', now()->toDateString());
                })
                ->where('warehouse_bins.is_blocked', false)
                ->where('warehouse_zones.zone_type', '!=', 'QUARANTINE')
                ->whereRaw('(inventory_balances.quantity_on_hand - inventory_balances.quantity_reserved) > 0')
                ->orderBy('material_lots.expiry_date', 'asc')
                ->orderBy('material_lots.created_at', 'asc')
                ->lockForUpdate()
                ->get();

            $allocations = [];
            $remainingNeeded = $requiredQuantity;

            foreach ($candidateBalances as $balance) {
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

                $allocations[] = [
                    'balance_id' => $balance->id,
                    'lot_id' => $lot->id,
                    'bin_id' => $bin->id,
                    'lot_number' => $lot->lot_number,
                    'bin_code' => $bin->bin_code,
                    'expiry_date' => $lot->expiry_date?->toDateString(),
                    'quantity' => $allocateQty,
                ];

                $remainingNeeded -= $allocateQty;

                if ($remainingNeeded <= 0.0001) {
                    break;
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

<?php

namespace App\Services;

use App\Models\CycleCountItem;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseAnalyticsService
{
    /**
     * Compute inventory aging buckets for active stock.
     */
    public function getInventoryAgingBreakdown(?string $warehouseId = null): array
    {
        $query = InventoryBalance::query()
            ->where('inventory_balances.quantity_on_hand', '>', 0)
            ->join('material_lots', 'inventory_balances.material_lot_id', '=', 'material_lots.id')
            ->join('warehouse_bins', 'inventory_balances.bin_id', '=', 'warehouse_bins.id')
            ->join('warehouse_zones', 'warehouse_bins.zone_id', '=', 'warehouse_zones.id')
            ->join('items', 'inventory_balances.item_id', '=', 'items.id')
            ->select([
                'inventory_balances.quantity_on_hand',
                'material_lots.id as lot_id',
                'material_lots.lot_number',
                'material_lots.lot_type',
                'material_lots.manufactured_date',
                'material_lots.expiry_date',
                'material_lots.created_at as lot_created_at',
                'material_lots.status as lot_status',
                'warehouse_bins.bin_code',
                'warehouse_zones.warehouse_id',
                'items.sku as item_sku',
                'items.name as item_name',
            ]);

        if ($warehouseId) {
            $query->where('warehouse_zones.warehouse_id', $warehouseId);
        }

        $records = $query->get();

        $totalKg = 0.0;
        $freshKg = 0.0;     // 0-30 days
        $normalKg = 0.0;    // 31-60 days
        $warningKg = 0.0;   // 61-90 days
        $criticalKg = 0.0;  // 90+ days
        $expiredKg = 0.0;

        $lotDetails = [];

        $now = now();

        foreach ($records as $row) {
            $qty = (float) $row->quantity_on_hand;
            $totalKg += $qty;

            $dateBase = $row->manufactured_date
                ? Carbon::parse($row->manufactured_date)
                : Carbon::parse($row->lot_created_at);

            $ageDays = max(0, (int) $dateBase->diffInDays($now));
            $isExpired = $row->expiry_date && Carbon::parse($row->expiry_date)->isPast();

            if ($isExpired) {
                $category = 'EXPIRED';
                $expiredKg += $qty;
            } elseif ($ageDays <= 30) {
                $category = '0-30 DAYS (FRESH)';
                $freshKg += $qty;
            } elseif ($ageDays <= 60) {
                $category = '31-60 DAYS (NORMAL)';
                $normalKg += $qty;
            } elseif ($ageDays <= 90) {
                $category = '61-90 DAYS (WARNING)';
                $warningKg += $qty;
            } else {
                $category = '90+ DAYS (CRITICAL)';
                $criticalKg += $qty;
            }

            $lotDetails[] = [
                'lot_number' => $row->lot_number,
                'item_sku' => $row->item_sku,
                'item_name' => $row->item_name,
                'bin_code' => $row->bin_code,
                'quantity_kg' => $qty,
                'age_days' => $ageDays,
                'expiry_date' => $row->expiry_date ? Carbon::parse($row->expiry_date)->format('Y-m-d') : 'N/A',
                'is_expired' => $isExpired,
                'category' => $category,
                'lot_status' => $row->lot_status,
            ];
        }

        $criticalPercentage = $totalKg > 0 ? round(($criticalKg / $totalKg) * 100, 2) : 0.0;

        return [
            'total_stock_kg' => round($totalKg, 3),
            'fresh_kg' => round($freshKg, 3),
            'normal_kg' => round($normalKg, 3),
            'warning_kg' => round($warningKg, 3),
            'critical_kg' => round($criticalKg, 3),
            'expired_kg' => round($expiredKg, 3),
            'critical_percentage' => $criticalPercentage,
            'lot_details' => collect($lotDetails)->sortByDesc('age_days')->values()->all(),
        ];
    }

    /**
     * Compute Inventory Record Accuracy (IRA) and shrinkage metrics from cycle counts.
     */
    public function getInventoryRecordAccuracy(): array
    {
        $items = CycleCountItem::query()
            ->whereIn('status', [CycleCountItem::STATUS_COUNTED, CycleCountItem::STATUS_APPROVED])
            ->with(['item', 'bin', 'order'])
            ->get();

        $totalAudited = $items->count();

        if ($totalAudited === 0) {
            return [
                'ira_percentage' => 100.0,
                'total_audited' => 0,
                'exact_matches' => 0,
                'net_variance_kg' => 0.0,
                'abs_variance_kg' => 0.0,
                'top_discrepancies' => [],
            ];
        }

        $exactMatches = 0;
        $netVariance = 0.0;
        $absVariance = 0.0;
        $discrepancies = [];

        foreach ($items as $item) {
            $variance = (float) $item->variance_quantity;
            $netVariance += $variance;
            $absVariance += abs($variance);

            if (abs($variance) < 0.001) {
                $exactMatches++;
            } else {
                $discrepancies[] = [
                    'order_number' => $item->order->count_number ?? 'N/A',
                    'item_sku' => $item->item->sku ?? 'N/A',
                    'item_name' => $item->item->name ?? 'N/A',
                    'bin_code' => $item->bin->bin_code ?? 'N/A',
                    'system_qty' => (float) $item->recorded_quantity,
                    'counted_qty' => (float) $item->counted_quantity,
                    'variance_qty' => $variance,
                    'abs_variance' => abs($variance),
                ];
            }
        }

        $iraPercentage = round(($exactMatches / $totalAudited) * 100, 2);

        usort($discrepancies, fn ($a, $b) => $b['abs_variance'] <=> $a['abs_variance']);

        return [
            'ira_percentage' => $iraPercentage,
            'total_audited' => $totalAudited,
            'exact_matches' => $exactMatches,
            'net_variance_kg' => round($netVariance, 3),
            'abs_variance_kg' => round($absVariance, 3),
            'top_discrepancies' => array_slice($discrepancies, 0, 5),
        ];
    }

    /**
     * Compute ABC movement velocity classification based on transactional ledger activity.
     */
    public function getVelocityAbcClassification(int $days = 90): array
    {
        $since = now()->subDays($days);

        $activity = InventoryMovement::query()
            ->where('created_at', '>=', $since)
            ->groupBy('item_id')
            ->select('item_id', DB::raw('COUNT(*) as movement_count'), DB::raw('SUM(quantity) as total_volume'))
            ->orderByDesc('movement_count')
            ->get();

        $totalTransactions = $activity->sum('movement_count');

        $allItems = Item::where('is_active', true)->get()->keyBy('id');

        if ($totalTransactions === 0 || $activity->isEmpty()) {
            return [
                'class_a_count' => 0,
                'class_b_count' => 0,
                'class_c_count' => $allItems->count(),
                'total_transactions' => 0,
                'items' => $allItems->map(fn ($item) => [
                    'item_id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'class' => 'C',
                    'movement_count' => 0,
                    'volume_kg' => 0.0,
                ])->values()->all(),
            ];
        }

        $cumulativeCount = 0;
        $classified = [];
        $classACount = 0;
        $classBCount = 0;
        $classCCount = 0;

        foreach ($activity as $row) {
            $item = $allItems->get($row->item_id);
            if (! $item) {
                continue;
            }

            $cumulativeCount += $row->movement_count;
            $percent = ($cumulativeCount / $totalTransactions) * 100;

            if ($classACount === 0 || $percent <= 80.0) {
                $class = 'A';
                $classACount++;
            } elseif ($classBCount === 0 || $percent <= 95.0) {
                $class = 'B';
                $classBCount++;
            } else {
                $class = 'C';
                $classCCount++;
            }

            $classified[] = [
                'item_id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'class' => $class,
                'movement_count' => (int) $row->movement_count,
                'volume_kg' => round((float) $row->total_volume, 2),
            ];
        }

        // Include any active items that had zero movements as Class C
        $activeItemIdsWithMovement = $activity->pluck('item_id')->all();
        foreach ($allItems as $item) {
            if (! in_array($item->id, $activeItemIdsWithMovement)) {
                $classCCount++;
                $classified[] = [
                    'item_id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'class' => 'C',
                    'movement_count' => 0,
                    'volume_kg' => 0.0,
                ];
            }
        }

        return [
            'class_a_count' => $classACount,
            'class_b_count' => $classBCount,
            'class_c_count' => $classCCount,
            'total_transactions' => $totalTransactions,
            'items' => $classified,
        ];
    }

    /**
     * Compute facility space and bin utilization.
     */
    public function getCapacityUtilization(): array
    {
        $totalBins = WarehouseBin::count();
        $occupiedBins = WarehouseBin::whereHas('balances', fn ($q) => $q->where('quantity_on_hand', '>', 0))->count();
        $blockedBins = WarehouseBin::where('is_blocked', true)->count();
        $pickFaceBins = WarehouseBin::where('is_pick_face', true)->count();

        $occupancyRate = $totalBins > 0 ? round(($occupiedBins / $totalBins) * 100, 1) : 0.0;

        $zones = WarehouseZone::withCount('bins')
            ->get()
            ->map(function ($zone) {
                $binIds = $zone->bins()->pluck('id');
                $occupied = WarehouseBin::whereIn('id', $binIds)
                    ->whereHas('balances', fn ($q) => $q->where('quantity_on_hand', '>', 0))
                    ->count();

                $storedKg = (float) InventoryBalance::whereIn('bin_id', $binIds)->sum('quantity_on_hand');

                return [
                    'zone' => $zone->code,
                    'total_bins' => (int) $zone->bins_count,
                    'occupied_bins' => $occupied,
                    'occupancy_pct' => $zone->bins_count > 0 ? round(($occupied / $zone->bins_count) * 100, 1) : 0,
                    'stored_kg' => round($storedKg, 2),
                ];
            });

        return [
            'total_bins' => $totalBins,
            'occupied_bins' => $occupiedBins,
            'occupancy_rate' => $occupancyRate,
            'blocked_bins' => $blockedBins,
            'pick_face_bins' => $pickFaceBins,
            'zones' => $zones->all(),
        ];
    }

    /**
     * Compute physical stock on-hand grouped by item category.
     */
    public function getCategoryStockBreakdown(): array
    {
        $allCategories = [
            Item::CATEGORY_RAW_RESIN => 'Raw Resin',
            Item::CATEGORY_MASTERBATCH => 'Masterbatch',
            Item::CATEGORY_FINISHED_GOOD => 'Finished Goods',
            Item::CATEGORY_PACKAGING => 'Packaging',
        ];

        $stock = InventoryBalance::query()
            ->where('quantity_on_hand', '>', 0)
            ->join('items', 'inventory_balances.item_id', '=', 'items.id')
            ->select('items.category', DB::raw('SUM(quantity_on_hand) as total_kg'), DB::raw('COUNT(DISTINCT inventory_balances.item_id) as sku_count'))
            ->groupBy('items.category')
            ->get()
            ->keyBy('category');

        $result = [];

        foreach ($allCategories as $categoryKey => $categoryLabel) {
            $row = $stock->get($categoryKey);
            $result[] = [
                'category' => $categoryKey,
                'label' => $categoryLabel,
                'total_kg' => $row ? round((float) $row->total_kg, 2) : 0.0,
                'sku_count' => $row ? (int) $row->sku_count : 0,
            ];
        }

        return $result;
    }

    /**
     * Generate RFC 4180 CSV export for inventory aging.
     */
    public function generateAgingCsv(): string
    {
        $data = $this->getInventoryAgingBreakdown();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Lot Number', 'SKU', 'Item Name', 'Bin Code', 'Quantity (KG)', 'Age (Days)', 'Expiry Date', 'Aging Category', 'Status']);

        foreach ($data['lot_details'] as $row) {
            fputcsv($output, [
                $row['lot_number'],
                $row['item_sku'],
                $row['item_name'],
                $row['bin_code'],
                $row['quantity_kg'],
                $row['age_days'],
                $row['expiry_date'],
                $row['category'],
                $row['lot_status'],
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Generate RFC 4180 CSV export for IRA cycle count variances.
     */
    public function generateIraCsv(): string
    {
        $items = CycleCountItem::query()
            ->whereIn('status', [CycleCountItem::STATUS_COUNTED, CycleCountItem::STATUS_APPROVED])
            ->with(['item', 'bin', 'order'])
            ->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Audit Ref', 'SKU', 'Item Name', 'Bin Code', 'System Qty', 'Counted Qty', 'Variance Qty', 'Accurate Match', 'Counted At']);

        foreach ($items as $item) {
            $isAccurate = abs((float) $item->variance_quantity) < 0.001 ? 'YES' : 'NO';
            fputcsv($output, [
                $item->order->count_number ?? 'N/A',
                $item->item->sku ?? 'N/A',
                $item->item->name ?? 'N/A',
                $item->bin->bin_code ?? 'N/A',
                (float) $item->recorded_quantity,
                (float) $item->counted_quantity,
                (float) $item->variance_quantity,
                $isAccurate,
                $item->counted_at ? $item->counted_at->format('Y-m-d H:i:s') : 'N/A',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}

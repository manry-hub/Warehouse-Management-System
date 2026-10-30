<?php

namespace App\Services;

use App\Models\WarehouseBin;
use App\Models\WarehouseZone;

class WarehouseHeatmapService
{
    public const STATUS_EMPTY = 'EMPTY';

    public const STATUS_OPTIMAL = 'OPTIMAL';

    public const STATUS_WARNING = 'WARNING';

    public const STATUS_CRITICAL = 'CRITICAL';

    public const STATUS_BLOCKED = 'BLOCKED';

    public const STATUS_QUARANTINE = 'QUARANTINE';

    /**
     * Compute zone-level aggregated capacity KPIs.
     */
    public function getZoneMetrics(WarehouseZone $zone): array
    {
        $bins = WarehouseBin::where('zone_id', $zone->id)
            ->with(['balances'])
            ->get();

        $totalBins = $bins->count();
        $occupiedBins = 0;
        $blockedBins = 0;
        $totalMaxWeightKg = 0.0;
        $totalStoredWeightKg = 0.0;

        foreach ($bins as $bin) {
            $binStoredWeight = (float) $bin->balances->sum('quantity_on_hand');
            $maxWeight = (float) $bin->max_weight_kg;

            $totalMaxWeightKg += $maxWeight;
            $totalStoredWeightKg += $binStoredWeight;

            if ($bin->is_blocked) {
                $blockedBins++;
            }

            if ($binStoredWeight > 0.0) {
                $occupiedBins++;
            }
        }

        $emptyBins = max(0, $totalBins - $occupiedBins);
        $occupancyRate = $totalBins > 0 ? round(($occupiedBins / $totalBins) * 100, 1) : 0.0;
        $weightUtilization = $totalMaxWeightKg > 0 ? round(($totalStoredWeightKg / $totalMaxWeightKg) * 100, 1) : 0.0;
        $availableHeadroomKg = max(0.0, $totalMaxWeightKg - $totalStoredWeightKg);

        return [
            'total_bins' => $totalBins,
            'occupied_bins' => $occupiedBins,
            'empty_bins' => $emptyBins,
            'blocked_bins' => $blockedBins,
            'occupancy_rate_pct' => $occupancyRate,
            'weight_utilization_pct' => $weightUtilization,
            'total_max_weight_kg' => round($totalMaxWeightKg, 2),
            'total_stored_weight_kg' => round($totalStoredWeightKg, 2),
            'available_headroom_kg' => round($availableHeadroomKg, 2),
        ];
    }

    /**
     * Compute hierarchical rack bay & shelf topology matrix for 2D visualization.
     */
    public function getZoneTopology(WarehouseZone $zone, array $filters = []): array
    {
        $query = WarehouseBin::where('zone_id', $zone->id)
            ->with(['balances.lot', 'balances.item']);

        $bins = $query->get();

        $filterSku = isset($filters['sku']) ? trim(strtoupper($filters['sku'])) : null;
        $filterStatus = isset($filters['status']) ? strtoupper($filters['status']) : 'ALL';

        $aisles = [];

        foreach ($bins as $bin) {
            $binData = $this->evaluateBin($bin, $zone);

            // Apply SKU filter
            if ($filterSku) {
                $matchesSku = false;
                foreach ($bin->balances as $balance) {
                    if (
                        str_contains(strtoupper($balance->item->sku ?? ''), $filterSku) ||
                        str_contains(strtoupper($balance->item->name ?? ''), $filterSku) ||
                        str_contains(strtoupper($balance->lot->lot_number ?? ''), $filterSku)
                    ) {
                        $matchesSku = true;
                        break;
                    }
                }
                if (! $matchesSku) {
                    continue;
                }
            }

            // Apply Status filter
            if ($filterStatus !== 'ALL') {
                if ($filterStatus === 'OCCUPIED' && $binData['stored_weight_kg'] <= 0.0) {
                    continue;
                }
                if ($filterStatus !== 'OCCUPIED' && $binData['status'] !== $filterStatus) {
                    continue;
                }
            }

            $aisleKey = $bin->aisle ?: 'General';
            $rackKey = $bin->rack ?: 'Floor';
            $shelfKey = $bin->shelf ?: '1';

            if (! isset($aisles[$aisleKey])) {
                $aisles[$aisleKey] = [
                    'aisle' => $aisleKey,
                    'racks' => [],
                ];
            }

            if (! isset($aisles[$aisleKey]['racks'][$rackKey])) {
                $aisles[$aisleKey]['racks'][$rackKey] = [
                    'rack' => $rackKey,
                    'shelves' => [],
                ];
            }

            if (! isset($aisles[$aisleKey]['racks'][$rackKey]['shelves'][$shelfKey])) {
                $aisles[$aisleKey]['racks'][$rackKey]['shelves'][$shelfKey] = [
                    'shelf' => $shelfKey,
                    'bins' => [],
                ];
            }

            $aisles[$aisleKey]['racks'][$rackKey]['shelves'][$shelfKey]['bins'][] = $binData;
        }

        // Sort aisles and racks naturally
        ksort($aisles, SORT_NATURAL);

        foreach ($aisles as &$aisleData) {
            ksort($aisleData['racks'], SORT_NATURAL);

            foreach ($aisleData['racks'] as &$rackData) {
                // Sort shelves descending so top tier is on top, ground tier at bottom
                krsort($rackData['shelves'], SORT_NATURAL);

                foreach ($rackData['shelves'] as &$shelfData) {
                    // Sort bins ascending from left position to right position
                    usort($shelfData['bins'], fn ($a, $b) => strnatcmp($a['bin'], $b['bin']));
                }
            }
        }

        return $aisles;
    }

    /**
     * Evaluate single bin metrics, fill status, and visual styling cues.
     */
    public function evaluateBin(WarehouseBin $bin, ?WarehouseZone $zone = null): array
    {
        $zone = $zone ?? $bin->zone;
        $storedWeight = (float) $bin->balances->sum('quantity_on_hand');
        $maxWeight = (float) ($bin->max_weight_kg ?: 1500.0);
        $fillPercentage = $maxWeight > 0 ? round(($storedWeight / $maxWeight) * 100, 1) : 0.0;

        $isQuarantineZone = $zone && $zone->zone_type === WarehouseZone::TYPE_QUARANTINE;

        $hasQuarantineLots = false;
        $activeSkus = [];
        foreach ($bin->balances as $balance) {
            if ($balance->quantity_on_hand > 0) {
                $sku = $balance->item->sku ?? 'ITEM';
                if (! in_array($sku, $activeSkus, true)) {
                    $activeSkus[] = $sku;
                }
                if ($balance->lot && in_array($balance->lot->status, ['QA_HOLD', 'QUARANTINE'], true)) {
                    $hasQuarantineLots = true;
                }
            }
        }

        // Determine bin state
        if ($bin->is_blocked) {
            $status = self::STATUS_BLOCKED;
        } elseif ($isQuarantineZone || $hasQuarantineLots) {
            $status = self::STATUS_QUARANTINE;
        } elseif ($storedWeight <= 0.0) {
            $status = self::STATUS_EMPTY;
        } elseif ($fillPercentage > 85.0) {
            $status = self::STATUS_CRITICAL;
        } elseif ($fillPercentage > 50.0) {
            $status = self::STATUS_WARNING;
        } else {
            $status = self::STATUS_OPTIMAL;
        }

        return [
            'id' => $bin->id,
            'bin_code' => $bin->bin_code,
            'aisle' => $bin->aisle,
            'rack' => $bin->rack,
            'shelf' => $bin->shelf,
            'bin' => $bin->bin,
            'max_weight_kg' => $maxWeight,
            'stored_weight_kg' => round($storedWeight, 2),
            'headroom_kg' => max(0.0, round($maxWeight - $storedWeight, 2)),
            'fill_percentage' => $fillPercentage,
            'is_blocked' => (bool) $bin->is_blocked,
            'is_quarantine' => $isQuarantineZone || $hasQuarantineLots,
            'status' => $status,
            'active_skus' => $activeSkus,
            'lots_count' => $bin->balances->where('quantity_on_hand', '>', 0)->count(),
        ];
    }

    /**
     * Get detailed bin inspection record including all balances and lots.
     */
    public function getBinDetails(string $binId): ?array
    {
        $bin = WarehouseBin::with(['zone.warehouse', 'balances.item', 'balances.lot'])
            ->find($binId);

        if (! $bin) {
            return null;
        }

        $evaluated = $this->evaluateBin($bin, $bin->zone);

        $balances = [];
        foreach ($bin->balances as $balance) {
            if ($balance->quantity_on_hand <= 0 && $balance->quantity_reserved <= 0) {
                continue;
            }

            $lot = $balance->lot;
            $balances[] = [
                'id' => $balance->id,
                'item_sku' => $balance->item->sku ?? 'N/A',
                'item_name' => $balance->item->name ?? 'Unknown Item',
                'category' => $balance->item->category ?? 'N/A',
                'lot_number' => $lot->lot_number ?? 'N/A',
                'lot_type' => $lot->lot_type ?? 'VIRGIN',
                'regrind_generation' => $lot->regrind_generation ?? 0,
                'regrind_machine' => $lot->regrind_source_machine ?? null,
                'status' => $lot->status ?? 'AVAILABLE',
                'expiry_date' => $lot->expiry_date ? $lot->expiry_date->format('Y-m-d') : null,
                'quantity_on_hand' => (float) $balance->quantity_on_hand,
                'quantity_reserved' => (float) $balance->quantity_reserved,
                'available_quantity' => (float) $balance->available_quantity,
            ];
        }

        return array_merge($evaluated, [
            'warehouse_code' => $bin->zone->warehouse->code ?? 'N/A',
            'warehouse_name' => $bin->zone->warehouse->name ?? 'N/A',
            'zone_code' => $bin->zone->code ?? 'N/A',
            'zone_name' => $bin->zone->name ?? 'N/A',
            'zone_type' => $bin->zone->zone_type ?? 'N/A',
            'max_volume_cbm' => (float) ($bin->max_volume_cbm ?: 0.0),
            'balances' => $balances,
        ]);
    }

    /**
     * Toggle the blocked status of a bin.
     */
    public function toggleBinBlocked(string $binId): WarehouseBin
    {
        $bin = WarehouseBin::findOrFail($binId);
        $bin->is_blocked = ! $bin->is_blocked;
        $bin->save();

        return $bin;
    }
}

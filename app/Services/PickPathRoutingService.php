<?php

namespace App\Services;

use App\Models\WarehouseBin;
use Illuminate\Support\Collection;

class PickPathRoutingService
{
    /**
     * Sort pick items or bins using a Serpentine (S-Shape) routing heuristic.
     *
     * Odd aisles traverse racks in ASCENDING order (1 -> 2 -> 3...).
     * Even aisles traverse racks in DESCENDING order (...3 -> 2 -> 1).
     *
     * @param  Collection  $items  Collection of models or arrays with warehouse_bin or bin_code
     * @return Collection Sorted collection with 'pick_sequence' assigned
     */
    public function sortSerpentine(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        // Group items by Aisle
        $byAisle = $items->groupBy(function ($item) {
            $bin = $this->resolveBin($item);

            return strtoupper($bin->aisle ?? 'Z');
        });

        // Sort aisles alphabetically (A, B, C...)
        $sortedAisleKeys = $byAisle->keys()->sort()->values();

        $orderedResult = collect();
        $sequence = 1;

        foreach ($sortedAisleKeys as $aisleIndex => $aisleKey) {
            $aisleItems = $byAisle->get($aisleKey);
            $isOddAisle = ($aisleIndex % 2 === 0); // 0th index is 1st aisle (odd), 1st is 2nd (even)

            // Sort within aisle
            $sortedAisleItems = $aisleItems->sort(function ($a, $b) use ($isOddAisle) {
                $binA = $this->resolveBin($a);
                $binB = $this->resolveBin($b);

                $rackA = is_numeric($binA->rack ?? '') ? (int) $binA->rack : 999;
                $rackB = is_numeric($binB->rack ?? '') ? (int) $binB->rack : 999;

                if ($rackA !== $rackB) {
                    return $isOddAisle ? ($rackA <=> $rackB) : ($rackB <=> $rackA);
                }

                $shelfA = is_numeric($binA->shelf ?? '') ? (int) $binA->shelf : 999;
                $shelfB = is_numeric($binB->shelf ?? '') ? (int) $binB->shelf : 999;

                if ($shelfA !== $shelfB) {
                    return $shelfA <=> $shelfB;
                }

                return strcmp($binA->bin_code ?? '', $binB->bin_code ?? '');
            });

            foreach ($sortedAisleItems as $item) {
                if (is_object($item)) {
                    $item->pick_sequence = $sequence++;
                    $orderedResult->push($item);
                } elseif (is_array($item)) {
                    $item['pick_sequence'] = $sequence++;
                    $orderedResult->push($item);
                }
            }
        }

        return $orderedResult;
    }

    /**
     * Estimate physical travel distance in meters.
     *
     * Heuristic parameters:
     * - Rack-to-rack distance in same aisle: 3.5m
     * - Aisle-to-aisle transit distance: 12.0m
     * - Vertical shelf transition: 1.5m
     */
    public function estimateTravelDistance(Collection $orderedItems): float
    {
        if ($orderedItems->count() <= 1) {
            return 0.0;
        }

        $totalDistance = 0.0;
        $previousBin = null;

        foreach ($orderedItems as $item) {
            $currentBin = $this->resolveBin($item);

            if ($previousBin !== null && $currentBin !== null) {
                $sameAisle = strtoupper($previousBin->aisle ?? '') === strtoupper($currentBin->aisle ?? '');

                if ($sameAisle) {
                    $rackDiff = abs((int) ($currentBin->rack ?? 1) - (int) ($previousBin->rack ?? 1));
                    $shelfDiff = abs((int) ($currentBin->shelf ?? 1) - (int) ($previousBin->shelf ?? 1));
                    $totalDistance += ($rackDiff * 3.5) + ($shelfDiff * 1.5);
                } else {
                    // Changing aisle traversal
                    $totalDistance += 12.0;
                    $rackDiff = abs((int) ($currentBin->rack ?? 1) - (int) ($previousBin->rack ?? 1));
                    $totalDistance += ($rackDiff * 3.5);
                }
            }

            $previousBin = $currentBin;
        }

        return round($totalDistance, 1);
    }

    /**
     * Compare unoptimized vs S-Path distance savings.
     */
    public function calculateEfficiencyGain(Collection $unoptimizedItems, Collection $optimizedItems): array
    {
        $unoptimizedDistance = $this->estimateTravelDistance($unoptimizedItems);
        $optimizedDistance = $this->estimateTravelDistance($optimizedItems);

        $metersSaved = max(0.0, round($unoptimizedDistance - $optimizedDistance, 1));
        $percentSavings = $unoptimizedDistance > 0
            ? round(($metersSaved / $unoptimizedDistance) * 100, 1)
            : 0.0;

        return [
            'unoptimized_distance_meters' => $unoptimizedDistance,
            'optimized_distance_meters' => $optimizedDistance,
            'meters_saved' => $metersSaved,
            'percent_savings' => $percentSavings,
        ];
    }

    protected function resolveBin(mixed $item): ?WarehouseBin
    {
        if ($item instanceof WarehouseBin) {
            return $item;
        }

        if (is_object($item) && isset($item->bin) && $item->bin instanceof WarehouseBin) {
            return $item->bin;
        }

        if (is_object($item) && isset($item->warehouse_bin_id)) {
            return WarehouseBin::find($item->warehouse_bin_id);
        }

        return null;
    }
}

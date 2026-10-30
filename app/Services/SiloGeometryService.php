<?php

namespace App\Services;

use App\Models\Silo;

class SiloGeometryService
{
    /**
     * Compute volumetric and mass metrics from a raw sensor distance reading.
     *
     * @return array{
     *     material_height_m: float,
     *     volume_m3: float,
     *     weight_kg: float,
     *     fill_percentage: float,
     *     status: string
     * }
     */
    public function calculateMetrics(Silo $silo, float $distanceMeters): array
    {
        $totalHeight = (float) $silo->total_height_m;
        $diameter = (float) $silo->diameter_m;
        $coneHeight = (float) $silo->cone_height_m;
        $sensorDeadband = (float) $silo->sensor_deadband_m;
        $bulkDensity = (float) $silo->bulk_density_kg_m3;
        $maxCapacity = (float) $silo->max_capacity_kg;
        $reorderThreshold = (float) $silo->reorder_threshold_kg;
        $criticalThreshold = (float) $silo->critical_threshold_kg;

        $radius = $diameter / 2.0;

        // Calculate material height h = H - distance, clamped within physical bounds
        $rawMaterialHeight = $totalHeight - $distanceMeters;
        $maxMaterialHeight = max(0.0, $totalHeight - $sensorDeadband);
        $materialHeight = max(0.0, min($maxMaterialHeight, $rawMaterialHeight));

        $volumeM3 = $this->calculateVolume($radius, $totalHeight, $coneHeight, $materialHeight);
        $weightKg = round($volumeM3 * $bulkDensity, 2);

        $fillPercentage = $maxCapacity > 0
            ? round(min(100.0, max(0.0, ($weightKg / $maxCapacity) * 100.0)), 2)
            : 0.0;

        $status = $this->evaluateStatus($weightKg, $fillPercentage, $reorderThreshold, $criticalThreshold);

        return [
            'material_height_m' => round($materialHeight, 2),
            'volume_m3' => round($volumeM3, 3),
            'weight_kg' => $weightKg,
            'fill_percentage' => $fillPercentage,
            'status' => $status,
        ];
    }

    /**
     * Calculate volume combining conical hopper and vertical cylindrical barrel.
     */
    public function calculateVolume(float $radius, float $totalHeight, float $coneHeight, float $materialHeight): float
    {
        if ($materialHeight <= 0.0) {
            return 0.0;
        }

        // Case 1: Pure cylinder (no bottom cone)
        if ($coneHeight <= 0.0) {
            return M_PI * ($radius ** 2) * $materialHeight;
        }

        // Case 2: Material only inside conical bottom hopper
        if ($materialHeight <= $coneHeight) {
            // V = (1/3) * pi * r^2 * (h^3 / H_cone^2)
            return (1.0 / 3.0) * M_PI * ($radius ** 2) * (($materialHeight ** 3) / ($coneHeight ** 2));
        }

        // Case 3: Material completely fills cone and extends into vertical cylinder
        $coneVolume = (1.0 / 3.0) * M_PI * ($radius ** 2) * $coneHeight;
        $cylinderHeight = $materialHeight - $coneHeight;
        $cylinderVolume = M_PI * ($radius ** 2) * $cylinderHeight;

        return $coneVolume + $cylinderVolume;
    }

    /**
     * Determine silo status based on mass and fill percentage thresholds.
     */
    public function evaluateStatus(float $weightKg, float $fillPercentage, float $reorderThreshold, float $criticalThreshold): string
    {
        if ($weightKg <= $criticalThreshold || $fillPercentage <= 15.0) {
            return Silo::STATUS_CRITICAL;
        }

        if ($weightKg <= $reorderThreshold || $fillPercentage <= 35.0) {
            return Silo::STATUS_WARNING;
        }

        return Silo::STATUS_NORMAL;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WarehouseAnalyticsService;
use Illuminate\Http\JsonResponse;

class WarehouseAnalyticsController extends Controller
{
    public function __construct(
        protected WarehouseAnalyticsService $analyticsService
    ) {}

    /**
     * Get executive warehouse analytics summary for mobile terminals and executive dashboards.
     */
    public function summary(): JsonResponse
    {
        $aging = $this->analyticsService->getInventoryAgingBreakdown();
        $ira = $this->analyticsService->getInventoryRecordAccuracy();
        $utilization = $this->analyticsService->getCapacityUtilization();

        return response()->json([
            'success' => true,
            'aging' => [
                'total_stock_kg' => $aging['total_stock_kg'],
                'fresh_kg' => $aging['fresh_kg'],
                'normal_kg' => $aging['normal_kg'],
                'warning_kg' => $aging['warning_kg'],
                'critical_kg' => $aging['critical_kg'],
                'expired_kg' => $aging['expired_kg'],
                'critical_percentage' => $aging['critical_percentage'],
            ],
            'ira' => [
                'accuracy_percentage' => $ira['ira_percentage'],
                'audited_items_count' => $ira['total_audited'],
                'exact_matches_count' => $ira['exact_matches'],
                'net_variance_kg' => $ira['net_variance_kg'],
                'absolute_variance_kg' => $ira['abs_variance_kg'],
            ],
            'utilization' => [
                'total_bins' => $utilization['total_bins'],
                'occupied_bins' => $utilization['occupied_bins'],
                'occupancy_rate' => $utilization['occupancy_rate'],
                'blocked_bins' => $utilization['blocked_bins'],
            ],
        ]);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Services\WarehouseAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WarehouseAnalyticsOverviewWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $service = app(WarehouseAnalyticsService::class);
        $aging = $service->getInventoryAgingBreakdown();
        $ira = $service->getInventoryRecordAccuracy();
        $utilization = $service->getCapacityUtilization();

        $iraPct = $ira['ira_percentage'];
        $totalStock = number_format($aging['total_stock_kg'], 1);
        $criticalKg = number_format($aging['critical_kg'], 1);
        $occupancyPct = $utilization['occupancy_rate'];

        return [
            Stat::make('Inventory Record Accuracy (IRA)', "{$iraPct}%")
                ->description("{$ira['exact_matches']} exact matches of {$ira['total_audited']} audited lines")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($iraPct >= 95 ? 'success' : ($iraPct >= 85 ? 'warning' : 'danger'))
                ->chart([90, 92, 91, 94, 93, 95, (int) $iraPct]),

            Stat::make('Active Inventory On-Hand', "{$totalStock} KG")
                ->description('Fresh stock (0-30d): '.number_format($aging['fresh_kg'], 1).' KG')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->chart([800, 950, 1100, 1050, 1200, 1350, (int) $aging['total_stock_kg']]),

            Stat::make('Resin Aging Risk (90d+)', "{$criticalKg} KG")
                ->description("{$aging['critical_percentage']}% of active inventory in critical aging")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($aging['critical_kg'] > 0 ? 'danger' : 'success')
                ->chart([10, 25, 40, 30, 50, (int) $aging['critical_kg']]),

            Stat::make('Warehouse Occupancy', "{$occupancyPct}%")
                ->description("{$utilization['occupied_bins']} of {$utilization['total_bins']} bins populated")
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color($occupancyPct > 85 ? 'danger' : ($occupancyPct > 50 ? 'warning' : 'success'))
                ->chart([20, 24, 28, 25, 30, (int) $occupancyPct]),
        ];
    }
}

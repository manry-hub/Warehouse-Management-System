<?php

namespace App\Filament\Widgets;

use App\Models\DeliveryOrder;
use App\Models\InventoryBalance;
use App\Models\QualityQuarantine;
use App\Models\WarehouseBin;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsKpiOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // 1. Total Inventory On Hand (KG)
        $totalStockKg = (float) InventoryBalance::sum('quantity_on_hand');
        $formattedStock = number_format($totalStockKg, 1).' KG';

        // 2. Bin Occupancy Rate (%)
        $totalBins = WarehouseBin::count();
        $occupiedBins = WarehouseBin::whereHas('balances', function ($q) {
            $q->where('quantity_on_hand', '>', 0);
        })->count();
        $occupancyPct = $totalBins > 0 ? round(($occupiedBins / $totalBins) * 100, 1) : 0;

        // 3. Pending Outbound Delivery Orders
        $pendingDos = DeliveryOrder::whereIn('status', [
            DeliveryOrder::STATUS_DRAFT,
            DeliveryOrder::STATUS_PICKING,
            DeliveryOrder::STATUS_LOADED,
        ])->count();

        // 4. Active Quarantines & Quality Alerts
        $activeQuarantines = QualityQuarantine::where('status', QualityQuarantine::STATUS_ACTIVE)->count();

        return [
            Stat::make('Total Inventory On-Hand', $formattedStock)
                ->description('Active stock across all facility zones')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->chart([800, 950, 1100, 1050, 1200, 1350, (int) $totalStockKg]),

            Stat::make('Warehouse Occupancy', "{$occupancyPct}%")
                ->description("{$occupiedBins} of {$totalBins} bins populated")
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color($occupancyPct > 85 ? 'danger' : ($occupancyPct > 50 ? 'warning' : 'success'))
                ->chart([22, 25, 27, 28, 30, 32, (int) $occupancyPct]),

            Stat::make('Pending Deliveries', (string) $pendingDos)
                ->description('Queued for picking, packing or gate out')
                ->descriptionIcon('heroicon-m-truck')
                ->color($pendingDos > 0 ? 'warning' : 'gray')
                ->chart([0, 1, 2, 1, 3, 2, $pendingDos]),

            Stat::make('Quality Quarantines', (string) $activeQuarantines)
                ->description($activeQuarantines > 0 ? 'Active lot isolations requiring audit' : 'All warehouse stock cleared')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($activeQuarantines > 0 ? 'danger' : 'success')
                ->chart([0, 0, 1, 0, 0, 0, $activeQuarantines]),
        ];
    }
}

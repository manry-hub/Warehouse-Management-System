<?php

namespace App\Filament\Widgets;

use App\Services\WarehouseAnalyticsService;
use Filament\Widgets\ChartWidget;

class ZoneUtilizationChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Warehouse Zone Occupancy (Occupied vs Total Bins)';

    protected static ?string $description = 'Bin storage capacity utilization across warehouse zones';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $service = app(WarehouseAnalyticsService::class);
        $utilization = $service->getCapacityUtilization();

        $labels = [];
        $occupied = [];
        $total = [];

        foreach ($utilization['zones'] as $zone) {
            $labels[] = $zone['zone'];
            $occupied[] = (int) $zone['occupied_bins'];
            $total[] = (int) $zone['total_bins'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Occupied Bins',
                    'data' => $occupied,
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'Total Bins',
                    'data' => $total,
                    'backgroundColor' => '#3f3f46',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

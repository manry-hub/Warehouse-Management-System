<?php

namespace App\Filament\Widgets;

use App\Services\WarehouseAnalyticsService;
use Filament\Widgets\ChartWidget;

class ResinAgingChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Resin Shelf-Life Aging Distribution (KG)';

    protected static ?string $description = 'Active resin lots grouped by shelf-life aging tier';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $service = app(WarehouseAnalyticsService::class);
        $aging = $service->getInventoryAgingBreakdown();

        return [
            'datasets' => [
                [
                    'label' => 'Inventory Weight (KG)',
                    'data' => [
                        (float) $aging['fresh_kg'],
                        (float) $aging['normal_kg'],
                        (float) $aging['warning_kg'],
                        (float) $aging['critical_kg'],
                        (float) $aging['expired_kg'],
                    ],
                    'backgroundColor' => [
                        '#10b981', // 0-30d Emerald
                        '#0ea5e9', // 31-60d Sky
                        '#f59e0b', // 61-90d Amber
                        '#f43f5e', // 90+d Rose
                        '#a855f7', // Expired Purple
                    ],
                ],
            ],
            'labels' => [
                '0-30d Fresh',
                '31-60d Normal',
                '61-90d Warning',
                '90d+ Critical',
                'Expired',
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'display' => false,
                ],
                'y' => [
                    'display' => false,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

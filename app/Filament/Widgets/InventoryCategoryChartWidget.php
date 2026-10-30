<?php

namespace App\Filament\Widgets;

use App\Services\WarehouseAnalyticsService;
use Filament\Widgets\ChartWidget;

class InventoryCategoryChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Stock Weight by Material Category (KG)';

    protected static ?string $description = 'Active physical inventory on-hand grouped by material type';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 5;

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $service = app(WarehouseAnalyticsService::class);
        $categories = $service->getCategoryStockBreakdown();

        $labels = [];
        $weights = [];

        foreach ($categories as $cat) {
            $labels[] = "{$cat['label']} ({$cat['sku_count']} SKUs)";
            $weights[] = (float) $cat['total_kg'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Stock Weight (KG)',
                    'data' => $weights,
                    'backgroundColor' => [
                        '#0ea5e9', // Sky: Raw Resin
                        '#8b5cf6', // Violet: Masterbatch
                        '#10b981', // Emerald: Finished Goods
                        '#f59e0b', // Amber: Packaging
                    ],
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

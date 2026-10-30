<?php

namespace App\Filament\Widgets;

use App\Services\WarehouseAnalyticsService;
use Filament\Widgets\ChartWidget;

class AbcVelocityChartWidget extends ChartWidget
{
    protected static ?string $heading = 'ABC Movement Velocity Share';

    protected static ?string $description = 'Pareto distribution of catalog turnover & movement volume';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $service = app(WarehouseAnalyticsService::class);
        $abc = $service->getVelocityAbcClassification();

        $volA = 0.0;
        $volB = 0.0;
        $volC = 0.0;

        foreach ($abc['items'] as $item) {
            if ($item['class'] === 'A') {
                $volA += $item['volume_kg'];
            } elseif ($item['class'] === 'B') {
                $volB += $item['volume_kg'];
            } else {
                $volC += $item['volume_kg'];
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Movement Volume (KG)',
                    'data' => [
                        round($volA, 1),
                        round($volB, 1),
                        round($volC, 1),
                    ],
                    'backgroundColor' => [
                        '#10b981', // Class A: Fast Movers (Emerald)
                        '#f59e0b', // Class B: Medium Turnover (Amber)
                        '#64748b', // Class C: Slow/Idle Catalog (Slate)
                    ],
                ],
            ],
            'labels' => [
                "Class A: Fast Movers ({$abc['class_a_count']} SKUs)",
                "Class B: Medium ({$abc['class_b_count']} SKUs)",
                "Class C: Slow/Idle ({$abc['class_c_count']} SKUs)",
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

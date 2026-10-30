<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AbcVelocityChartWidget;
use App\Filament\Widgets\AgingLotsTableWidget;
use App\Filament\Widgets\CycleCountDiscrepancyTableWidget;
use App\Filament\Widgets\InventoryCategoryChartWidget;
use App\Filament\Widgets\ResinAgingChartWidget;
use App\Filament\Widgets\WarehouseAnalyticsOverviewWidget;
use App\Filament\Widgets\ZoneUtilizationChartWidget;
use App\Services\WarehouseAnalyticsService;
use Filament\Actions;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseAnalyticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Audit & Ledger';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Executive Analytics & Compliance';

    protected static ?string $slug = 'executive-analytics';

    protected static string $view = 'filament.pages.warehouse-analytics';

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WarehouseAnalyticsOverviewWidget::class,
            ResinAgingChartWidget::class,
            AbcVelocityChartWidget::class,
            ZoneUtilizationChartWidget::class,
            InventoryCategoryChartWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            AgingLotsTableWidget::class,
            CycleCountDiscrepancyTableWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_aging_csv')
                ->label('Export Aging CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function (WarehouseAnalyticsService $service): StreamedResponse {
                    $csv = $service->generateAgingCsv();

                    return response()->streamDownload(
                        function () use ($csv) {
                            echo $csv;
                        },
                        'wms_inventory_aging_'.now()->format('Ymd_His').'.csv',
                        ['Content-Type' => 'text/csv']
                    );
                }),

            Actions\Action::make('export_ira_csv')
                ->label('Export IRA Audit CSV')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(function (WarehouseAnalyticsService $service): StreamedResponse {
                    $csv = $service->generateIraCsv();

                    return response()->streamDownload(
                        function () use ($csv) {
                            echo $csv;
                        },
                        'wms_ira_cycle_counts_'.now()->format('Ymd_His').'.csv',
                        ['Content-Type' => 'text/csv']
                    );
                }),
        ];
    }
}

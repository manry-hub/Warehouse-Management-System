<?php

namespace App\Filament\Pages;

use App\Models\Warehouse;
use App\Models\WarehouseZone;
use App\Services\WarehouseHeatmapService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WarehouseHeatmapPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Warehouse Topology';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Visual Bin Topology & Heatmap';

    protected static ?string $slug = 'visual-heatmap';

    protected static string $view = 'filament.pages.warehouse-heatmap';

    public ?string $selectedWarehouseId = null;

    public ?string $selectedZoneId = null;

    public string $filterSku = '';

    public string $filterStatus = 'ALL';

    public string $viewMode = 'matrix'; // 'matrix' (2D Floor Plan) or 'elevation' (Rack Shelf)

    public ?string $selectedBinId = null;

    public ?array $activeBinDetails = null;

    public function mount(): void
    {
        $defaultWarehouse = Warehouse::where('code', 'WH-MAIN')->where('is_active', true)->first()
            ?? Warehouse::where('is_active', true)->first();

        if ($defaultWarehouse) {
            $this->selectedWarehouseId = $defaultWarehouse->id;
            $defaultZone = WarehouseZone::where('warehouse_id', $defaultWarehouse->id)->first();
            if ($defaultZone) {
                $this->selectedZoneId = $defaultZone->id;
            }
        }
    }

    public function updatedSelectedWarehouseId($value): void
    {
        $firstZone = WarehouseZone::where('warehouse_id', $value)->first();
        $this->selectedZoneId = $firstZone?->id;
        $this->closeDrawer();
    }

    public function updatedSelectedZoneId(): void
    {
        $this->closeDrawer();
    }

    public function selectBin(string $binId): void
    {
        $this->selectedBinId = $binId;
        $heatmapService = app(WarehouseHeatmapService::class);
        $this->activeBinDetails = $heatmapService->getBinDetails($binId);
        $this->dispatch('open-modal', id: 'bin-modal');
    }

    public function closeDrawer(): void
    {
        $this->selectedBinId = null;
        $this->activeBinDetails = null;
        $this->dispatch('close-modal', id: 'bin-modal');
    }

    public function toggleBlocked(string $binId): void
    {
        $heatmapService = app(WarehouseHeatmapService::class);
        $bin = $heatmapService->toggleBinBlocked($binId);

        if ($this->selectedBinId === $binId) {
            $this->activeBinDetails = $heatmapService->getBinDetails($binId);
        }

        Notification::make()
            ->title($bin->is_blocked ? "Bin {$bin->bin_code} Blocked" : "Bin {$bin->bin_code} Unblocked")
            ->body($bin->is_blocked ? 'Bin has been locked from putaway and picking.' : 'Bin is now active and accessible.')
            ->success()
            ->send();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['matrix', 'elevation'], true) ? $mode : 'matrix';
    }

    public function resetFilters(): void
    {
        $this->filterSku = '';
        $this->filterStatus = 'ALL';
    }

    public function getWarehousesProperty()
    {
        return Warehouse::where('is_active', true)->orderBy('name')->get();
    }

    public function getSelectedWarehouseProperty(): ?Warehouse
    {
        if (! $this->selectedWarehouseId) {
            return null;
        }

        return Warehouse::find($this->selectedWarehouseId);
    }

    public function getZonesProperty()
    {
        if (! $this->selectedWarehouseId) {
            return collect();
        }

        return WarehouseZone::where('warehouse_id', $this->selectedWarehouseId)->orderBy('name')->get();
    }

    public function getSelectedZoneProperty(): ?WarehouseZone
    {
        if (! $this->selectedZoneId) {
            return null;
        }

        return WarehouseZone::find($this->selectedZoneId);
    }

    public function getZoneMetricsProperty(): array
    {
        $zone = $this->selectedZone;
        if (! $zone) {
            return [];
        }

        return app(WarehouseHeatmapService::class)->getZoneMetrics($zone);
    }

    public function getStats(): array
    {
        $warehouse = $this->selectedWarehouse;
        $metrics = $warehouse
            ? app(WarehouseHeatmapService::class)->getWarehouseMetrics($warehouse)
            : $this->zoneMetrics;

        if (empty($metrics)) {
            return [];
        }

        return [
            Stat::make('Warehouse Bins', (string) $metrics['total_bins'])
                ->description('Configured facility slots')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('gray'),

            Stat::make('Facility Occupancy', "{$metrics['occupancy_rate_pct']}%")
                ->description("{$metrics['occupied_bins']} / {$metrics['total_bins']} bins occupied")
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($metrics['occupancy_rate_pct'] > 85 ? 'danger' : ($metrics['occupancy_rate_pct'] > 50 ? 'warning' : 'success')),

            Stat::make('Payload Utilization', "{$metrics['weight_utilization_pct']}%")
                ->description('Of total warehouse cap')
                ->descriptionIcon('heroicon-m-scale')
                ->color($metrics['weight_utilization_pct'] > 85 ? 'danger' : ($metrics['weight_utilization_pct'] > 50 ? 'warning' : 'primary')),

            Stat::make('Total Stored Stock', number_format($metrics['total_stored_weight_kg'], 1).' kg')
                ->description('Max: '.number_format($metrics['total_max_weight_kg'], 0).' kg')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('primary'),

            Stat::make('Facility Headroom', number_format($metrics['available_headroom_kg'], 1).' kg')
                ->description("{$metrics['empty_bins']} bins fully empty")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Blocked Slots', (string) $metrics['blocked_bins'])
                ->description('Locked from operations')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($metrics['blocked_bins'] > 0 ? 'danger' : 'gray'),
        ];
    }

    public function getWarehouseTopologyProperty(): array
    {
        $warehouse = $this->selectedWarehouse;
        if (! $warehouse) {
            return [];
        }

        return app(WarehouseHeatmapService::class)->getWarehouseTopology($warehouse, [
            'sku' => $this->filterSku,
            'status' => $this->filterStatus,
        ]);
    }

    public function getTopologyProperty(): array
    {
        $zone = $this->selectedZone;
        if (! $zone) {
            return [];
        }

        return app(WarehouseHeatmapService::class)->getZoneTopology($zone, [
            'sku' => $this->filterSku,
            'status' => $this->filterStatus,
        ]);
    }
}

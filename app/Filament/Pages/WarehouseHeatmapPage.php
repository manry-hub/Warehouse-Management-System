<?php

namespace App\Filament\Pages;

use App\Models\Warehouse;
use App\Models\WarehouseZone;
use App\Services\WarehouseHeatmapService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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

    public ?string $selectedBinId = null;

    public ?array $activeBinDetails = null;

    public function mount(): void
    {
        $defaultWarehouse = Warehouse::where('is_active', true)->first();
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

    public function resetFilters(): void
    {
        $this->filterSku = '';
        $this->filterStatus = 'ALL';
    }

    public function getWarehousesProperty()
    {
        return Warehouse::where('is_active', true)->orderBy('name')->get();
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

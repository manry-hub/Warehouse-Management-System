<?php

namespace App\Filament\Resources\WarehouseBinResource\Pages;

use App\Filament\Pages\WarehouseHeatmapPage;
use App\Filament\Resources\WarehouseBinResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseBins extends ListRecords
{
    protected static string $resource = WarehouseBinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('visualHeatmap')
                ->label('2D Visual Heatmap')
                ->icon('heroicon-o-squares-2x2')
                ->color('warning')
                ->url(WarehouseHeatmapPage::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}

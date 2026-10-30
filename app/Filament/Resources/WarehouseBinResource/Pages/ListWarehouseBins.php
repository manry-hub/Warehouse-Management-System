<?php

namespace App\Filament\Resources\WarehouseBinResource\Pages;

use App\Filament\Resources\WarehouseBinResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseBins extends ListRecords
{
    protected static string $resource = WarehouseBinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

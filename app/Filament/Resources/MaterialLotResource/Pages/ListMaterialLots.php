<?php

namespace App\Filament\Resources\MaterialLotResource\Pages;

use App\Filament\Resources\MaterialLotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialLots extends ListRecords
{
    protected static string $resource = MaterialLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

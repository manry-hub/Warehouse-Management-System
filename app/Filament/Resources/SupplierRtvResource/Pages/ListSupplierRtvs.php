<?php

namespace App\Filament\Resources\SupplierRtvResource\Pages;

use App\Filament\Resources\SupplierRtvResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupplierRtvs extends ListRecords
{
    protected static string $resource = SupplierRtvResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Create Supplier RTV'),
        ];
    }
}

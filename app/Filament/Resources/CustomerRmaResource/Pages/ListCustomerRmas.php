<?php

namespace App\Filament\Resources\CustomerRmaResource\Pages;

use App\Filament\Resources\CustomerRmaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerRmas extends ListRecords
{
    protected static string $resource = CustomerRmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Initiate Customer RMA'),
        ];
    }
}

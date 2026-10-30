<?php

namespace App\Filament\Resources\CycleCountOrderResource\Pages;

use App\Filament\Resources\CycleCountOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCycleCountOrders extends ListRecords
{
    protected static string $resource = CycleCountOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

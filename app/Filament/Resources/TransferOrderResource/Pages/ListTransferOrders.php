<?php

namespace App\Filament\Resources\TransferOrderResource\Pages;

use App\Filament\Resources\TransferOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransferOrders extends ListRecords
{
    protected static string $resource = TransferOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\CustomerRmaResource\Pages;

use App\Filament\Resources\CustomerRmaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerRma extends CreateRecord
{
    protected static string $resource = CustomerRmaResource::class;

    protected function afterCreate(): void
    {
        $this->record->update([
            'total_cartons' => $this->record->items()->count(),
        ]);
    }
}

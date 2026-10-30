<?php

namespace App\Filament\Resources\CustomerRmaResource\Pages;

use App\Filament\Resources\CustomerRmaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerRma extends EditRecord
{
    protected static string $resource = CustomerRmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->update([
            'total_cartons' => $this->record->items()->count(),
        ]);
    }
}

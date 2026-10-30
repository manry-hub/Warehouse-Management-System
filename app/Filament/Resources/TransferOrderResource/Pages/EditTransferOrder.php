<?php

namespace App\Filament\Resources\TransferOrderResource\Pages;

use App\Filament\Resources\TransferOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransferOrder extends EditRecord
{
    protected static string $resource = TransferOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

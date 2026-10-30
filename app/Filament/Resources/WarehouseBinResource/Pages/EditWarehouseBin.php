<?php

namespace App\Filament\Resources\WarehouseBinResource\Pages;

use App\Filament\Resources\WarehouseBinResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseBin extends EditRecord
{
    protected static string $resource = WarehouseBinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

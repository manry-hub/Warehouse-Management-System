<?php

namespace App\Filament\Resources\SupplierRtvResource\Pages;

use App\Filament\Resources\SupplierRtvResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplierRtv extends EditRecord
{
    protected static string $resource = SupplierRtvResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

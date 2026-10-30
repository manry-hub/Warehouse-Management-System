<?php

namespace App\Filament\Resources\CycleCountOrderResource\Pages;

use App\Filament\Resources\CycleCountOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCycleCountOrder extends EditRecord
{
    protected static string $resource = CycleCountOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

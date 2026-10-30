<?php

namespace App\Filament\Resources\PickingWaveResource\Pages;

use App\Filament\Resources\PickingWaveResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPickingWave extends EditRecord
{
    protected static string $resource = PickingWaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\ReplenishmentTaskResource\Pages;

use App\Filament\Resources\ReplenishmentTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReplenishmentTask extends EditRecord
{
    protected static string $resource = ReplenishmentTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

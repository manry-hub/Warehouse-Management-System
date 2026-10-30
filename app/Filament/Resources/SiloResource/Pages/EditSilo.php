<?php

namespace App\Filament\Resources\SiloResource\Pages;

use App\Filament\Resources\SiloResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSilo extends EditRecord
{
    protected static string $resource = SiloResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

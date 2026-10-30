<?php

namespace App\Filament\Resources\ReplenishmentTaskResource\Pages;

use App\Filament\Resources\ReplenishmentTaskResource;
use App\Services\ReplenishmentService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListReplenishmentTasks extends ListRecords
{
    protected static string $resource = ReplenishmentTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('evaluate_deficits')
                ->label('Scan Pick Face Deficits')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->action(function (ReplenishmentService $service) {
                    $spawned = $service->evaluateDeficits();

                    if ($spawned->isEmpty()) {
                        Notification::make()
                            ->title('All Pick Faces Stocked')
                            ->body('No inventory deficits detected. Forward-pick bins meet minimum thresholds.')
                            ->info()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Replenishment Triggered')
                            ->body("Generated {$spawned->count()} replenishment missions for forklift operators.")
                            ->success()
                            ->send();
                    }
                }),

            Actions\CreateAction::make()->label('Manual Task'),
        ];
    }
}

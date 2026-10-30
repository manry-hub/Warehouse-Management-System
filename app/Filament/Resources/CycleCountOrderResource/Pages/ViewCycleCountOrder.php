<?php

namespace App\Filament\Resources\CycleCountOrderResource\Pages;

use App\Filament\Resources\CycleCountOrderResource;
use App\Models\CycleCountOrder;
use App\Models\User;
use App\Services\CycleCountService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCycleCountOrder extends ViewRecord
{
    protected static string $resource = CycleCountOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('startCount')
                ->label('Start Count (Freeze Snapshot)')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->visible(fn (CycleCountOrder $record) => $record->canStart())
                ->requiresConfirmation()
                ->action(function (CycleCountOrder $record, CycleCountService $service) {
                    $service->startCount($record);
                    Notification::make()
                        ->title('Cycle Count Started')
                        ->body("Snapshot recorded. {$record->items()->count()} inventory items ready for counting.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('approveOrder')
                ->label('Approve Count')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (CycleCountOrder $record) => $record->canApprove())
                ->requiresConfirmation()
                ->action(function (CycleCountOrder $record, CycleCountService $service) {
                    $service->approveOrder($record, auth()->user() ?? User::first());
                    Notification::make()
                        ->title('Count Approved')
                        ->body("Order {$record->count_number} approved for adjustment posting.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('postAdjustments')
                ->label('Post Ledger Adjustments')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->visible(fn (CycleCountOrder $record) => $record->canPost())
                ->requiresConfirmation()
                ->modalDescription('This will post immutable double-entry ADJUSTMENT movements to the inventory ledger and update physical stock balances.')
                ->action(function (CycleCountOrder $record, CycleCountService $service) {
                    $result = $service->postAdjustments($record, auth()->user() ?? User::first());
                    Notification::make()
                        ->title('Adjustments Posted to Ledger')
                        ->body("Recorded {$result['movements_recorded']} movements. Surplus: +{$result['total_surplus']} KG, Shortage: -{$result['total_shortage']} KG.")
                        ->success()
                        ->send();
                }),

            Actions\EditAction::make(),
        ];
    }
}

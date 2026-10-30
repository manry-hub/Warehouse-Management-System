<?php

namespace App\Filament\Resources\TransferOrderResource\Pages;

use App\Filament\Resources\TransferOrderResource;
use App\Models\TransferOrder;
use App\Services\TransferOrderService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTransferOrder extends ViewRecord
{
    protected static string $resource = TransferOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve Transfer')
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->visible(fn (TransferOrder $record) => $record->status === TransferOrder::STATUS_DRAFT)
                ->action(function (TransferOrder $record, TransferOrderService $service) {
                    $service->approveOrder($record);
                    Notification::make()->title('Transfer Order Approved')->success()->send();
                }),

            Actions\Action::make('dispatch')
                ->label('Dispatch Truck')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->visible(fn (TransferOrder $record) => $record->status === TransferOrder::STATUS_APPROVED)
                ->form([
                    Forms\Components\TextInput::make('carrier_name')
                        ->label('Logistics Carrier')
                        ->required()
                        ->default('Internal Dedicated Fleet'),
                    Forms\Components\TextInput::make('vehicle_plate_number')
                        ->label('Truck Plate Number')
                        ->required()
                        ->placeholder('e.g. B 9872 KXY'),
                    Forms\Components\TextInput::make('waybill_number')
                        ->label('Bill of Lading / Waybill #')
                        ->required()
                        ->default(fn (TransferOrder $record) => 'BOL-'.$record->order_number),
                ])
                ->action(function (TransferOrder $record, array $data, TransferOrderService $service) {
                    $service->dispatchOrder(
                        order: $record,
                        carrierName: $data['carrier_name'],
                        plateNumber: $data['vehicle_plate_number'],
                        waybillNumber: $data['waybill_number'],
                        user: auth()->user()
                    );
                    Notification::make()->title('Truck Dispatched into WH-TRANSIT')->warning()->send();
                }),

            Actions\Action::make('receive')
                ->label('Receive Truck at Destination')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn (TransferOrder $record) => $record->status === TransferOrder::STATUS_IN_TRANSIT)
                ->requiresConfirmation()
                ->action(function (TransferOrder $record, TransferOrderService $service) {
                    $result = $service->receiveOrder(order: $record, user: auth()->user());
                    $crossDocks = $result['cross_docks'];

                    $body = "Transfer {$record->order_number} received successfully.";
                    if (count($crossDocks) > 0) {
                        $body .= ' '.count($crossDocks).' lot(s) bypassed rack storage via Cross-Dock directly to outbound staging!';
                    }

                    Notification::make()
                        ->title('Transfer Order Received')
                        ->body($body)
                        ->success()
                        ->send();
                }),

            Actions\EditAction::make(),
        ];
    }
}

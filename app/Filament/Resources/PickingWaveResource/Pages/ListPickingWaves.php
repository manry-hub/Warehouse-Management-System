<?php

namespace App\Filament\Resources\PickingWaveResource\Pages;

use App\Filament\Resources\PickingWaveResource;
use App\Models\DeliveryOrder;
use App\Models\PickingWave;
use App\Services\WavePickingService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPickingWaves extends ListRecords
{
    protected static string $resource = PickingWaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_from_orders')
                ->label('Group Pending Orders into Wave')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('delivery_orders')
                        ->label('Select Pending Delivery Orders')
                        ->multiple()
                        ->options(function () {
                            return DeliveryOrder::whereIn('status', [DeliveryOrder::STATUS_DRAFT, DeliveryOrder::STATUS_PICKING])
                                ->doesntHave('pickingWaveOrders')
                                ->pluck('do_number', 'id');
                        })
                        ->required()
                        ->helperText('Orders will be consolidated and partitioned into 4-tote picking batches.'),

                    Forms\Components\Select::make('priority')
                        ->options([
                            PickingWave::PRIORITY_URGENT => 'Urgent',
                            PickingWave::PRIORITY_NORMAL => 'Normal',
                            PickingWave::PRIORITY_LOW => 'Low',
                        ])
                        ->default(PickingWave::PRIORITY_NORMAL)
                        ->required(),

                    Forms\Components\DateTimePicker::make('cutoff_time')
                        ->label('Shipping Cutoff')
                        ->default(now()->addHours(4)),

                    Forms\Components\Textarea::make('notes')
                        ->placeholder('Optional wave dispatch instructions...'),
                ])
                ->action(function (array $data, WavePickingService $service) {
                    try {
                        $wave = $service->createWaveFromDeliveryOrders($data['delivery_orders'], [
                            'priority' => $data['priority'],
                            'cutoff_time' => $data['cutoff_time'],
                            'notes' => $data['notes'],
                        ]);

                        Notification::make()
                            ->title('Wave Created')
                            ->body("Picking Wave {$wave->wave_number} created with {$wave->total_orders} orders.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Creation Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\CreateAction::make()->label('Manual Wave Header'),
        ];
    }
}

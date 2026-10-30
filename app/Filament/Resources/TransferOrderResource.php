<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferOrderResource\Pages;
use App\Models\TransferOrder;
use App\Services\TransferOrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransferOrderResource extends Resource
{
    protected static ?string $model = TransferOrder::class;

    protected static ?string $navigationGroup = 'Fulfillment & Shipping';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transfer Order Routing')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Transfer Order #')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'XFER-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4))),
                        Forms\Components\Select::make('origin_warehouse_id')
                            ->label('Origin Facility')
                            ->relationship('originWarehouse', 'name', fn ($q) => $q->where('code', '!=', 'WH-TRANSIT'))
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('destination_warehouse_id')
                            ->label('Destination Facility')
                            ->relationship('destinationWarehouse', 'name', fn ($q) => $q->where('code', '!=', 'WH-TRANSIT'))
                            ->required()
                            ->searchable(),
                    ]),

                Forms\Components\Section::make('Transport & Freight Details')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('carrier_name')
                            ->label('Carrier / Fleet')
                            ->placeholder('e.g. Dedicated Plant Shuttle'),
                        Forms\Components\TextInput::make('vehicle_plate_number')
                            ->label('Vehicle Plate')
                            ->placeholder('e.g. B 9872 KXY'),
                        Forms\Components\TextInput::make('waybill_number')
                            ->label('BOL / Waybill')
                            ->placeholder('e.g. BOL-XFER-001'),
                        Forms\Components\Select::make('status')
                            ->options([
                                TransferOrder::STATUS_DRAFT => 'Draft',
                                TransferOrder::STATUS_APPROVED => 'Approved',
                                TransferOrder::STATUS_IN_TRANSIT => 'In Transit (WH-TRANSIT)',
                                TransferOrder::STATUS_RECEIVED => 'Received',
                                TransferOrder::STATUS_COMPLETED => 'Completed',
                                TransferOrder::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->default(TransferOrder::STATUS_DRAFT)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull()
                            ->placeholder('Inter-facility transfer notes...'),
                    ]),

                Forms\Components\Section::make('Transfer Line Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('item_id')
                                    ->label('Item / Part')
                                    ->relationship('item', 'name')
                                    ->required()
                                    ->searchable(),
                                Forms\Components\Select::make('material_lot_id')
                                    ->label('Material Lot')
                                    ->relationship('lot', 'lot_number')
                                    ->required()
                                    ->searchable(),
                                Forms\Components\Select::make('origin_bin_id')
                                    ->label('Origin Bin')
                                    ->relationship('originBin', 'bin_code')
                                    ->required()
                                    ->searchable(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Transfer Quantity')
                                    ->numeric()
                                    ->required(),
                                Forms\Components\Toggle::make('is_cross_docked')
                                    ->label('Cross-Docked Bypass')
                                    ->disabled(),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Transfer Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('originWarehouse.name')
                    ->label('Origin')
                    ->limit(20)
                    ->searchable(),
                Tables\Columns\TextColumn::make('destinationWarehouse.name')
                    ->label('Destination')
                    ->limit(20)
                    ->searchable(),
                Tables\Columns\TextColumn::make('carrier_name')
                    ->label('Carrier')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('vehicle_plate_number')
                    ->label('Plate #')
                    ->fontFamily('mono')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('waybill_number')
                    ->label('BOL #')
                    ->fontFamily('mono')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        TransferOrder::STATUS_DRAFT => 'gray',
                        TransferOrder::STATUS_APPROVED => 'info',
                        TransferOrder::STATUS_IN_TRANSIT => 'warning',
                        TransferOrder::STATUS_COMPLETED => 'success',
                        TransferOrder::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('dispatched_at')
                    ->label('Dispatched')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn (TransferOrder $record) => $record->status === TransferOrder::STATUS_DRAFT)
                    ->action(function (TransferOrder $record, TransferOrderService $service) {
                        $service->approveOrder($record);
                        Notification::make()->title('Transfer Order Approved')->success()->send();
                    }),
                Tables\Actions\Action::make('dispatch')
                    ->label('Dispatch')
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
                        Notification::make()->title('Dispatched to WH-TRANSIT')->warning()->send();
                    }),
                Tables\Actions\Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (TransferOrder $record) => $record->status === TransferOrder::STATUS_IN_TRANSIT)
                    ->requiresConfirmation()
                    ->action(function (TransferOrder $record, TransferOrderService $service) {
                        $result = $service->receiveOrder(order: $record, user: auth()->user());
                        $crossDocks = $result['cross_docks'];

                        $msg = 'Transfer received successfully.';
                        if (count($crossDocks) > 0) {
                            $msg .= ' '.count($crossDocks).' lot(s) cross-docked directly to outbound shipping dock!';
                        }

                        Notification::make()->title($msg)->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransferOrders::route('/'),
            'create' => Pages\CreateTransferOrder::route('/create'),
            'view' => Pages\ViewTransferOrder::route('/{record}'),
            'edit' => Pages\EditTransferOrder::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryOrderResource\Pages;
use App\Models\DeliveryOrder;
use App\Models\User;
use App\Services\DeliveryOrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrder::class;

    protected static ?string $navigationGroup = 'Fulfillment & Shipping';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Delivery Order Details')
                    ->schema([
                        Forms\Components\TextInput::make('do_number')
                            ->label('Delivery Order No')
                            ->required()
                            ->default(fn () => 'DO-'.date('Ymd').'-'.rand(100, 999))
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer / Consignee')
                            ->required(),
                        Forms\Components\Textarea::make('shipping_address')
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Transport & Manifest')
                    ->schema([
                        Forms\Components\TextInput::make('vehicle_plate_number')
                            ->label('Vehicle License Plate')
                            ->required(),
                        Forms\Components\TextInput::make('driver_name')
                            ->label('Driver Name')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                DeliveryOrder::STATUS_DRAFT => 'Draft',
                                DeliveryOrder::STATUS_PICKING => 'Picking / Scanning',
                                DeliveryOrder::STATUS_LOADED => 'Loaded',
                                DeliveryOrder::STATUS_DISPATCHED => 'Dispatched',
                                DeliveryOrder::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->default(DeliveryOrder::STATUS_DRAFT)
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('do_number')
                    ->label('Delivery Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle_plate_number')
                    ->label('Plate #')
                    ->searchable(),
                Tables\Columns\TextColumn::make('driver_name')
                    ->label('Driver')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_cartons')
                    ->label('Cartons')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => DeliveryOrder::STATUS_DRAFT,
                        'info' => DeliveryOrder::STATUS_PICKING,
                        'warning' => DeliveryOrder::STATUS_LOADED,
                        'success' => DeliveryOrder::STATUS_DISPATCHED,
                        'danger' => DeliveryOrder::STATUS_CANCELLED,
                    ]),
                Tables\Columns\TextColumn::make('dispatched_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('downloadPdf')
                    ->label('Delivery Order PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->action(function (DeliveryOrder $record, DeliveryOrderService $service) {
                        $pdfContent = $service->generatePdf($record);
                        $filename = "Delivery_Order_{$record->do_number}.pdf";

                        return response()->streamDownload(
                            fn () => print ($pdfContent),
                            $filename,
                            ['Content-Type' => 'application/pdf']
                        );
                    }),

                Tables\Actions\Action::make('dispatch')
                    ->label('Finalize Dispatch')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (DeliveryOrder $record) => $record->status === DeliveryOrder::STATUS_LOADED)
                    ->requiresConfirmation()
                    ->action(function (DeliveryOrder $record, DeliveryOrderService $service) {
                        $service->dispatch($record, auth()->user() ?? User::first());
                        Notification::make()
                            ->title('Shipment Dispatched')
                            ->body("Delivery Order {$record->do_number} recorded in inventory ledger.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryOrders::route('/'),
            'create' => Pages\CreateDeliveryOrder::route('/create'),
            'edit' => Pages\EditDeliveryOrder::route('/{record}/edit'),
        ];
    }
}

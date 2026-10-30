<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerRmaResource\Pages;
use App\Models\CustomerRma;
use App\Models\DeliveryOrder;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\WarehouseBin;
use App\Services\CustomerRmaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerRmaResource extends Resource
{
    protected static ?string $model = CustomerRma::class;

    protected static ?string $navigationGroup = 'Quality & Traceability';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?string $navigationLabel = 'Customer RMAs';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereNotIn('status', [
            CustomerRma::STATUS_RESOLVED,
            CustomerRma::STATUS_CANCELLED,
        ])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('RMA Information')
                    ->schema([
                        Forms\Components\TextInput::make('rma_number')
                            ->label('RMA Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Generated on creation'),

                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Select::make('delivery_order_id')
                            ->label('Delivery Order Ref')
                            ->options(DeliveryOrder::pluck('order_number', 'id'))
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Select::make('defect_reason')
                            ->label('Primary Defect Reason')
                            ->options([
                                'WARPAGE' => 'Warpage / Dimensional Distortion',
                                'FLASH' => 'Excess Flash / Burrs',
                                'SINK_MARK' => 'Sink Marks / Surface Depression',
                                'BURNT_MATERIAL' => 'Degraded / Burnt Polymer',
                                'SHORT_SHOT' => 'Incomplete Injection (Short Shot)',
                                'WRONG_ITEM' => 'Incorrect Item Delivered',
                                'TRANSIT_DAMAGE' => 'Transit / Packaging Damage',
                                'OTHER' => 'Other / Unspecified Quality Reject',
                            ])
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options([
                                CustomerRma::STATUS_PENDING_RECEIPT => 'Pending Receipt',
                                CustomerRma::STATUS_RECEIVED => 'Received (Dock)',
                                CustomerRma::STATUS_UNDER_INSPECTION => 'Under Inspection',
                                CustomerRma::STATUS_RESOLVED => 'Resolved',
                                CustomerRma::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->default(CustomerRma::STATUS_PENDING_RECEIPT)
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Select::make('disposition')
                            ->options([
                                CustomerRma::DISPOSITION_PENDING => 'Pending',
                                CustomerRma::DISPOSITION_RESTOCK => 'Restock to Finished Goods',
                                CustomerRma::DISPOSITION_SCRAP_REGRIND => 'Route to Granulator Regrind',
                                CustomerRma::DISPOSITION_SCRAP_DISPOSAL => 'Scrap Disposal (Write-off)',
                            ])
                            ->default(CustomerRma::DISPOSITION_PENDING)
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('credit_memo_number')
                            ->label('Credit Memo Number')
                            ->disabled(),

                        Forms\Components\Textarea::make('qc_inspection_notes')
                            ->label('QC Inspection Notes')
                            ->columnSpanFull()
                            ->rows(3),
                    ])->columns(2),

                Forms\Components\Section::make('RMA Line Items & Cartons')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('item_id')
                                    ->label('Item')
                                    ->options(Item::pluck('name', 'id'))
                                    ->required()
                                    ->searchable(),

                                Forms\Components\Select::make('material_lot_id')
                                    ->label('Original Lot')
                                    ->options(MaterialLot::pluck('lot_number', 'id'))
                                    ->searchable()
                                    ->nullable(),

                                Forms\Components\TextInput::make('carton_barcode')
                                    ->label('Carton Barcode')
                                    ->placeholder('e.g. CART-20261030-01'),

                                Forms\Components\TextInput::make('quantity_returned')
                                    ->label('Qty Returned')
                                    ->numeric()
                                    ->required(),

                                Forms\Components\TextInput::make('quantity_received')
                                    ->label('Qty Received')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled(),

                                Forms\Components\TextInput::make('disposition')
                                    ->label('Line Disposition')
                                    ->default(CustomerRma::DISPOSITION_PENDING)
                                    ->disabled(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rma_number')
                    ->label('RMA #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('deliveryOrder.order_number')
                    ->label('DO Ref')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('defect_reason')
                    ->label('Defect')
                    ->badge()
                    ->color('danger')
                    ->limit(25),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CustomerRma::STATUS_PENDING_RECEIPT => 'warning',
                        CustomerRma::STATUS_RECEIVED => 'info',
                        CustomerRma::STATUS_UNDER_INSPECTION => 'primary',
                        CustomerRma::STATUS_RESOLVED => 'success',
                        CustomerRma::STATUS_CANCELLED => 'gray',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('disposition')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CustomerRma::DISPOSITION_RESTOCK => 'success',
                        CustomerRma::DISPOSITION_SCRAP_REGRIND => 'warning',
                        CustomerRma::DISPOSITION_SCRAP_DISPOSAL => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('cartons_progress')
                    ->label('Cartons')
                    ->state(fn (CustomerRma $record): string => "{$record->received_cartons} / {$record->total_cartons}")
                    ->badge()
                    ->color(fn (CustomerRma $record): string => $record->received_cartons >= $record->total_cartons && $record->total_cartons > 0 ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('credit_memo_number')
                    ->label('Credit Memo')
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('disposition')
                    ->label('QC Disposition')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (CustomerRma $record): bool => ! in_array($record->status, [CustomerRma::STATUS_RESOLVED, CustomerRma::STATUS_CANCELLED]) && $record->received_cartons > 0)
                    ->form([
                        Forms\Components\Select::make('disposition')
                            ->label('QA Disposition Decision')
                            ->options([
                                CustomerRma::DISPOSITION_RESTOCK => 'Restock to Active Finished Goods Bin',
                                CustomerRma::DISPOSITION_SCRAP_REGRIND => 'Route to Granulator Regrind Staging',
                                CustomerRma::DISPOSITION_SCRAP_DISPOSAL => 'Scrap Disposal (Permanent Write-off)',
                            ])
                            ->required()
                            ->reactive(),

                        Forms\Components\Select::make('quarantine_bin_id')
                            ->label('Quarantine Source Bin')
                            ->options(fn () => WarehouseBin::where('bin_code', 'like', '%QUARANTINE%')->pluck('bin_code', 'id')->all() ?: WarehouseBin::pluck('bin_code', 'id')->all())
                            ->default(fn () => WarehouseBin::where('bin_code', 'like', '%QUARANTINE%')->first()?->id ?? WarehouseBin::first()?->id)
                            ->required(),

                        Forms\Components\Select::make('target_bin_id')
                            ->label('Destination Bin (Restock or Regrind)')
                            ->options(fn () => WarehouseBin::where('bin_code', 'not like', '%QUARANTINE%')->pluck('bin_code', 'id')->all())
                            ->required(fn (Forms\Get $get) => in_array($get('disposition'), [CustomerRma::DISPOSITION_RESTOCK, CustomerRma::DISPOSITION_SCRAP_REGRIND]))
                            ->visible(fn (Forms\Get $get) => in_array($get('disposition'), [CustomerRma::DISPOSITION_RESTOCK, CustomerRma::DISPOSITION_SCRAP_REGRIND])),

                        Forms\Components\Textarea::make('notes')
                            ->label('Inspection Report & Root Cause Notes')
                            ->rows(3),
                    ])
                    ->action(function (CustomerRma $record, array $data, CustomerRmaService $service) {
                        try {
                            $quarantineBin = WarehouseBin::findOrFail($data['quarantine_bin_id']);
                            $targetBin = ! empty($data['target_bin_id']) ? WarehouseBin::find($data['target_bin_id']) : null;

                            $service->processDisposition(
                                rma: $record,
                                disposition: $data['disposition'],
                                quarantineBin: $quarantineBin,
                                targetBin: $targetBin,
                                inspector: auth()->user(),
                                notes: $data['notes'] ?? null
                            );

                            Notification::make()
                                ->title('Disposition Completed')
                                ->body("RMA {$record->rma_number} disposition executed as {$data['disposition']}. Credit memo generated.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Disposition Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerRmas::route('/'),
            'create' => Pages\CreateCustomerRma::route('/create'),
            'edit' => Pages\EditCustomerRma::route('/{record}/edit'),
        ];
    }
}

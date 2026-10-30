<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierRtvResource\Pages;
use App\Models\MaterialLot;
use App\Models\SupplierRtv;
use App\Models\WarehouseBin;
use App\Services\SupplierRtvService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierRtvResource extends Resource
{
    protected static ?string $model = SupplierRtv::class;

    protected static ?string $navigationGroup = 'Quality & Traceability';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Supplier RTVs';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', [
            SupplierRtv::STATUS_DRAFT,
            SupplierRtv::STATUS_APPROVED,
        ])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $hasApproved = static::getModel()::where('status', SupplierRtv::STATUS_APPROVED)->exists();

        return $hasApproved ? 'danger' : 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('RTV Header Information')
                    ->schema([
                        Forms\Components\TextInput::make('rtv_number')
                            ->label('RTV Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Generated on creation'),

                        Forms\Components\Select::make('material_lot_id')
                            ->label('Defective Material Lot')
                            ->options(MaterialLot::with('item')->get()->mapWithKeys(fn ($lot) => [
                                $lot->id => "{$lot->lot_number} ({$lot->item->name})",
                            ]))
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($lot = MaterialLot::find($state)) {
                                    $set('vendor_code', $lot->vendor_code ?? 'VND-GEN');
                                }
                            }),

                        Forms\Components\TextInput::make('vendor_code')
                            ->label('Vendor Code')
                            ->required()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('vendor_name')
                            ->label('Vendor Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('po_number')
                            ->label('Purchase Order Ref')
                            ->maxLength(50),

                        Forms\Components\Select::make('warehouse_bin_id')
                            ->label('Source Staging / Quarantine Bin')
                            ->options(WarehouseBin::pluck('bin_code', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('quantity_kg')
                            ->label('Return Quantity (KG)')
                            ->numeric()
                            ->required()
                            ->suffix('kg'),

                        Forms\Components\Select::make('reason')
                            ->label('Return Reason')
                            ->options([
                                'Failed Melt Flow Index (MFI)' => 'Failed Melt Flow Index (MFI)',
                                'Moisture Contamination' => 'Moisture / Water Contamination',
                                'Color / Masterbatch Deviation' => 'Color / Masterbatch Deviation',
                                'Density Mismatch' => 'Density / Specific Gravity Mismatch',
                                'Foreign Particulate / Contaminant' => 'Foreign Particulate / Contaminant',
                                'Torn Packaging / Water Damage' => 'Torn Packaging / Water Damage',
                                'Expired Shelf-Life / Vendor Recall' => 'Expired Shelf-Life / Vendor Recall',
                                'Other Supplier Quality Reject' => 'Other Supplier Quality Reject',
                            ])
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options([
                                SupplierRtv::STATUS_DRAFT => 'Draft',
                                SupplierRtv::STATUS_APPROVED => 'Approved for Dispatch',
                                SupplierRtv::STATUS_DISPATCHED => 'Dispatched',
                                SupplierRtv::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->default(SupplierRtv::STATUS_DRAFT)
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('debit_memo_number')
                            ->label('Debit Memo Ref')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rtv_number')
                    ->label('RTV #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('vendor_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('materialLot.lot_number')
                    ->label('Lot #')
                    ->searchable(),

                Tables\Columns\TextColumn::make('warehouseBin.bin_code')
                    ->label('Source Bin')
                    ->badge()
                    ->color('secondary'),

                Tables\Columns\TextColumn::make('quantity_kg')
                    ->label('Qty (kg)')
                    ->numeric(2)
                    ->suffix(' kg')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SupplierRtv::STATUS_DRAFT => 'warning',
                        SupplierRtv::STATUS_APPROVED => 'info',
                        SupplierRtv::STATUS_DISPATCHED => 'success',
                        SupplierRtv::STATUS_CANCELLED => 'gray',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('debit_memo_number')
                    ->label('Debit Memo')
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve RTV')
                    ->icon('heroicon-o-check')
                    ->color('primary')
                    ->visible(fn (SupplierRtv $record): bool => $record->status === SupplierRtv::STATUS_DRAFT)
                    ->requiresConfirmation()
                    ->action(function (SupplierRtv $record, SupplierRtvService $service) {
                        $service->approveRtv($record);
                        Notification::make()
                            ->title('RTV Approved')
                            ->body("RTV {$record->rtv_number} approved for logistics dispatch.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('dispatch')
                    ->label('Dispatch & Debit Memo')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (SupplierRtv $record): bool => $record->status === SupplierRtv::STATUS_APPROVED)
                    ->form([
                        Forms\Components\TextInput::make('debit_memo_number')
                            ->label('Debit Memo Reference')
                            ->default(fn () => sprintf('DM-%s-%03d', now()->format('Ymd'), SupplierRtv::whereNotNull('debit_memo_number')->count() + 1))
                            ->required(),
                    ])
                    ->action(function (SupplierRtv $record, array $data, SupplierRtvService $service) {
                        try {
                            $res = $service->dispatchRtv(
                                rtv: $record,
                                debitMemoNumber: $data['debit_memo_number'],
                                user: auth()->user()
                            );

                            Notification::make()
                                ->title('RTV Dispatched')
                                ->body($res['message'])
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Dispatch Failed')
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
            'index' => Pages\ListSupplierRtvs::route('/'),
            'create' => Pages\CreateSupplierRtv::route('/create'),
            'edit' => Pages\EditSupplierRtv::route('/{record}/edit'),
        ];
    }
}

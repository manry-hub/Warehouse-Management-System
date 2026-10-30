<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryMovementResource\Pages;
use App\Models\InventoryMovement;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $navigationGroup = 'Audit & Ledger';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // Ledger is append-only through domain services
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'success' => [InventoryMovement::TYPE_RECEIPT, InventoryMovement::TYPE_FG_INTAKE],
                        'info' => InventoryMovement::TYPE_PUTAWAY,
                        'warning' => InventoryMovement::TYPE_STAGING_ISSUE,
                        'danger' => InventoryMovement::TYPE_SHIPMENT,
                    ]),
                Tables\Columns\TextColumn::make('item.sku')
                    ->label('Item SKU')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('lot.lot_number')
                    ->label('Lot Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sourceBin.bin_code')
                    ->label('From Bin')
                    ->placeholder('(External / Dock)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('destinationBin.bin_code')
                    ->label('To Bin')
                    ->placeholder('(Dispatched / Customer)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric(3)
                    ->sortable(),
                Tables\Columns\TextColumn::make('uom')
                    ->label('UOM'),
                Tables\Columns\TextColumn::make('reference_id')
                    ->label('Reference Doc')
                    ->searchable(),
                Tables\Columns\TextColumn::make('transaction_uuid')
                    ->label('TX UUID')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('movement_type')
                    ->options([
                        InventoryMovement::TYPE_RECEIPT => 'Receipt',
                        InventoryMovement::TYPE_PUTAWAY => 'Putaway',
                        InventoryMovement::TYPE_STAGING_ISSUE => 'Staging Issue',
                        InventoryMovement::TYPE_FG_INTAKE => 'FG Intake',
                        InventoryMovement::TYPE_SHIPMENT => 'Shipment',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryMovements::route('/'),
        ];
    }
}

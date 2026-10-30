<?php

namespace App\Filament\Widgets;

use App\Models\InventoryBalance;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AgingLotsTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Active Material Lots & Aging Profile';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryBalance::query()
                    ->where('quantity_on_hand', '>', 0)
                    ->with(['lot', 'item', 'bin'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('lot.lot_number')
                    ->label('Lot Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('item.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item Name')
                    ->limit(25)
                    ->searchable(),

                Tables\Columns\TextColumn::make('bin.bin_code')
                    ->label('Bin')
                    ->badge(),

                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label('Qty (KG)')
                    ->numeric(2)
                    ->suffix(' kg')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lot.manufactured_date')
                    ->label('Mfg Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lot.expiry_date')
                    ->label('Expiry Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lot_age')
                    ->label('Age')
                    ->state(function (InventoryBalance $record): string {
                        $date = $record->lot?->manufactured_date ?? $record->lot?->created_at ?? $record->created_at;

                        return $date ? $date->diffInDays(now()).' d' : 'N/A';
                    })
                    ->badge()
                    ->color(function (InventoryBalance $record): string {
                        if ($record->lot?->expiry_date && $record->lot->expiry_date->isPast()) {
                            return 'gray';
                        }
                        $date = $record->lot?->manufactured_date ?? $record->lot?->created_at ?? $record->created_at;
                        $age = $date ? $date->diffInDays(now()) : 0;

                        return $age > 90 ? 'danger' : ($age > 60 ? 'warning' : 'success');
                    }),

                Tables\Columns\TextColumn::make('lot.status')
                    ->label('Lot Status')
                    ->badge(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}

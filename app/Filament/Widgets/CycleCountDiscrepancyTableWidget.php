<?php

namespace App\Filament\Widgets;

use App\Models\CycleCountItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CycleCountDiscrepancyTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Cycle Count Material Variances & IRA Reconciliation';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CycleCountItem::query()
                    ->whereIn('status', [CycleCountItem::STATUS_COUNTED, CycleCountItem::STATUS_APPROVED])
                    ->with(['item', 'bin', 'order'])
                    ->latest('counted_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('order.count_number')
                    ->label('Audit #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('item.sku')
                    ->label('SKU')
                    ->searchable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item Name')
                    ->limit(25),

                Tables\Columns\TextColumn::make('bin.bin_code')
                    ->label('Bin')
                    ->badge(),

                Tables\Columns\TextColumn::make('recorded_quantity')
                    ->label('System Qty')
                    ->numeric(2)
                    ->suffix(' kg')
                    ->sortable(),

                Tables\Columns\TextColumn::make('counted_quantity')
                    ->label('Counted Qty')
                    ->numeric(2)
                    ->suffix(' kg')
                    ->sortable(),

                Tables\Columns\TextColumn::make('variance_quantity')
                    ->label('Variance')
                    ->badge()
                    ->color(fn (string $state): string => (float) $state < 0 ? 'danger' : ((float) $state > 0 ? 'success' : 'gray'))
                    ->formatStateUsing(fn (string $state): string => ((float) $state > 0 ? '+' : '').number_format((float) $state, 2).' kg')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CycleCountItem::STATUS_APPROVED => 'success',
                        CycleCountItem::STATUS_COUNTED => 'info',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('counted_at')
                    ->label('Audited At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}

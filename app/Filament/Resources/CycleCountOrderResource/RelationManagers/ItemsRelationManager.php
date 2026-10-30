<?php

namespace App\Filament\Resources\CycleCountOrderResource\RelationManagers;

use App\Models\CycleCountItem;
use App\Services\CycleCountService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Count Sheet & Discrepancy Reconciliation';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('counted_quantity')
                    ->label('Counted Physical Quantity')
                    ->numeric()
                    ->required(),
                Forms\Components\Textarea::make('counter_notes')
                    ->label('Counter Notes'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('bin.bin_code')
                    ->label('Bin Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('item.sku')
                    ->label('Item SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item Name')
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->item?->name),

                Tables\Columns\TextColumn::make('materialLot.lot_number')
                    ->label('Lot Number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('recorded_quantity')
                    ->label('Recorded')
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' KG')
                    ->sortable(),

                Tables\Columns\TextColumn::make('counted_quantity')
                    ->label('Counted')
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' KG')
                    ->placeholder('Pending')
                    ->sortable(),

                Tables\Columns\TextColumn::make('variance_quantity')
                    ->label('Variance')
                    ->formatStateUsing(function ($state) {
                        if ($state === null) {
                            return '—';
                        }
                        $val = (float) $state;
                        if (abs($val) < 0.0001) {
                            return '0.000 (Match)';
                        }

                        return ($val > 0 ? "+{$val}" : "{$val}").' KG';
                    })
                    ->badge()
                    ->colors([
                        'gray' => fn ($state) => $state === null,
                        'success' => fn ($state) => $state !== null && abs((float) $state) < 0.0001,
                        'warning' => fn ($state) => $state !== null && (float) $state > 0.0001,
                        'danger' => fn ($state) => $state !== null && (float) $state < -0.0001,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => CycleCountItem::STATUS_PENDING,
                        'info' => CycleCountItem::STATUS_COUNTED,
                        'warning' => CycleCountItem::STATUS_RECOUNT_REQUESTED,
                        'success' => CycleCountItem::STATUS_APPROVED,
                    ]),

                Tables\Columns\TextColumn::make('counted_at')
                    ->label('Counted At')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        CycleCountItem::STATUS_PENDING => 'Pending',
                        CycleCountItem::STATUS_COUNTED => 'Counted',
                        CycleCountItem::STATUS_RECOUNT_REQUESTED => 'Recount Requested',
                        CycleCountItem::STATUS_APPROVED => 'Approved',
                    ]),
                Tables\Filters\Filter::make('has_variance')
                    ->label('Variances Only')
                    ->query(fn ($query) => $query->whereRaw('ABS(variance_quantity) > 0.0001')),
            ])
            ->actions([
                Tables\Actions\Action::make('recount')
                    ->label('Request Recount')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('reason')
                            ->label('Recount Reason')
                            ->required()
                            ->placeholder('e.g. Discrepancy > 5 KG, recount requested'),
                    ])
                    ->action(function (CycleCountItem $record, array $data, CycleCountService $service) {
                        $service->requestRecount($record, $data['reason']);
                        Notification::make()
                            ->title('Recount Requested')
                            ->body("Bin {$record->bin->bin_code} marked for recount.")
                            ->warning()
                            ->send();
                    }),
            ]);
    }
}

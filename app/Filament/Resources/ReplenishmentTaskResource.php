<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReplenishmentTaskResource\Pages;
use App\Models\ReplenishmentTask;
use App\Models\User;
use App\Services\ReplenishmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReplenishmentTaskResource extends Resource
{
    protected static ?string $model = ReplenishmentTask::class;

    protected static ?string $navigationGroup = 'Fulfillment & Shipping';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', [
            ReplenishmentTask::STATUS_PENDING,
            ReplenishmentTask::STATUS_ASSIGNED,
            ReplenishmentTask::STATUS_IN_TRANSIT,
        ])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $hasCritical = static::getModel()::where('status', ReplenishmentTask::STATUS_PENDING)
            ->where('priority', ReplenishmentTask::PRIORITY_CRITICAL)
            ->exists();

        return $hasCritical ? 'danger' : 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Replenishment Mission')
                    ->schema([
                        Forms\Components\TextInput::make('task_number')
                            ->required()
                            ->readOnly(),

                        Forms\Components\Select::make('priority')
                            ->options([
                                ReplenishmentTask::PRIORITY_CRITICAL => 'Critical (Stockout)',
                                ReplenishmentTask::PRIORITY_URGENT => 'Urgent (<50% Min)',
                                ReplenishmentTask::PRIORITY_NORMAL => 'Normal Top-Off',
                            ])
                            ->required(),

                        Forms\Components\Select::make('target_bin_id')
                            ->label('Target Pick Face Bin')
                            ->relationship('targetBin', 'bin_code')
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('source_bin_id')
                            ->label('Source Bulk Reserve Bin')
                            ->relationship('sourceBin', 'bin_code')
                            ->searchable(),

                        Forms\Components\Select::make('item_id')
                            ->relationship('item', 'name')
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('material_lot_id')
                            ->label('Allocated FEFO Lot')
                            ->relationship('materialLot', 'lot_number')
                            ->searchable(),

                        Forms\Components\TextInput::make('requested_quantity_kg')
                            ->label('Deficit Quantity (KG)')
                            ->numeric()
                            ->required(),

                        Forms\Components\Select::make('assigned_to')
                            ->label('Forklift Operator')
                            ->options(User::pluck('name', 'id'))
                            ->searchable(),

                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('task_number')
                    ->label('Task #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ReplenishmentTask::PRIORITY_CRITICAL => 'danger',
                        ReplenishmentTask::PRIORITY_URGENT => 'warning',
                        ReplenishmentTask::PRIORITY_NORMAL => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ReplenishmentTask::STATUS_PENDING => 'warning',
                        ReplenishmentTask::STATUS_ASSIGNED => 'info',
                        ReplenishmentTask::STATUS_IN_TRANSIT => 'info',
                        ReplenishmentTask::STATUS_COMPLETED => 'success',
                        ReplenishmentTask::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('targetBin.bin_code')
                    ->label('Pick Face')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('info'),

                Tables\Columns\TextColumn::make('sourceBin.bin_code')
                    ->label('Bulk Source')
                    ->searchable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('item.item_code')
                    ->label('SKU')
                    ->searchable(),

                Tables\Columns\TextColumn::make('materialLot.lot_number')
                    ->label('FEFO Lot')
                    ->searchable(),

                Tables\Columns\TextColumn::make('requested_quantity_kg')
                    ->label('Deficit')
                    ->numeric(2)
                    ->suffix(' KG')
                    ->sortable(),

                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Driver')
                    ->placeholder('Unassigned'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        ReplenishmentTask::STATUS_PENDING => 'Pending',
                        ReplenishmentTask::STATUS_ASSIGNED => 'Assigned',
                        ReplenishmentTask::STATUS_IN_TRANSIT => 'In Transit',
                        ReplenishmentTask::STATUS_COMPLETED => 'Completed',
                        ReplenishmentTask::STATUS_CANCELLED => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        ReplenishmentTask::PRIORITY_CRITICAL => 'Critical',
                        ReplenishmentTask::PRIORITY_URGENT => 'Urgent',
                        ReplenishmentTask::PRIORITY_NORMAL => 'Normal',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ReplenishmentTask $record) => in_array($record->status, [ReplenishmentTask::STATUS_PENDING, ReplenishmentTask::STATUS_ASSIGNED]))
                    ->requiresConfirmation()
                    ->action(function (ReplenishmentTask $record, ReplenishmentService $service) {
                        $service->cancelTask($record, 'Cancelled by dispatcher from admin table.');
                        Notification::make()
                            ->title('Task Cancelled')
                            ->body('Bulk reserve allocation has been released back to stock.')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReplenishmentTasks::route('/'),
            'create' => Pages\CreateReplenishmentTask::route('/create'),
            'edit' => Pages\EditReplenishmentTask::route('/{record}/edit'),
        ];
    }
}

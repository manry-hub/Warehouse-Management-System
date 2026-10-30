<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PickingWaveResource\Pages;
use App\Models\PickingBatchItem;
use App\Models\PickingWave;
use App\Models\User;
use App\Services\WavePickingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PickingWaveResource extends Resource
{
    protected static ?string $model = PickingWave::class;

    protected static ?string $navigationGroup = 'Fulfillment & Shipping';

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', [PickingWave::STATUS_RELEASED, PickingWave::STATUS_IN_PROGRESS])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Wave Header & Parameters')
                    ->schema([
                        Forms\Components\TextInput::make('wave_number')
                            ->label('Wave Identifier')
                            ->default(fn () => sprintf('WAV-%s-%03d', now()->format('Ymd'), rand(100, 999)))
                            ->required()
                            ->readOnly(),

                        Forms\Components\Select::make('wave_type')
                            ->label('Fulfillment Type')
                            ->options([
                                PickingWave::TYPE_DELIVERY_ORDERS => 'Outbound Delivery Orders',
                                PickingWave::TYPE_STAGING_DEMANDS => 'MES Shop-Floor Staging',
                            ])
                            ->default(PickingWave::TYPE_DELIVERY_ORDERS)
                            ->required(),

                        Forms\Components\Select::make('priority')
                            ->options([
                                PickingWave::PRIORITY_URGENT => 'Urgent (Line Down / Express)',
                                PickingWave::PRIORITY_NORMAL => 'Normal Batch',
                                PickingWave::PRIORITY_LOW => 'Low / Backfill',
                            ])
                            ->default(PickingWave::PRIORITY_NORMAL)
                            ->required(),

                        Forms\Components\DateTimePicker::make('cutoff_time')
                            ->label('Fulfillment Cutoff')
                            ->default(now()->addHours(4)),

                        Forms\Components\Select::make('assigned_to')
                            ->label('Lead Wave Picker')
                            ->options(User::pluck('name', 'id'))
                            ->searchable(),

                        Forms\Components\Textarea::make('notes')
                            ->placeholder('Special handling instructions, shift assignments, or staging bay constraints...')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('wave_number')
                    ->label('Wave #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PickingWave::STATUS_DRAFT => 'gray',
                        PickingWave::STATUS_RELEASED => 'warning',
                        PickingWave::STATUS_IN_PROGRESS => 'info',
                        PickingWave::STATUS_COMPLETED => 'success',
                        PickingWave::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PickingWave::PRIORITY_URGENT => 'danger',
                        PickingWave::PRIORITY_NORMAL => 'primary',
                        PickingWave::PRIORITY_LOW => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('progress')
                    ->label('Pick Progress')
                    ->state(fn (PickingWave $record): string => "{$record->picked_lines} / {$record->total_lines} lines")
                    ->description(fn (PickingWave $record): string => $record->total_lines > 0
                        ? round(($record->picked_lines / $record->total_lines) * 100).'% picked'
                        : '0%'),

                Tables\Columns\TextColumn::make('batches_count')
                    ->label('Batches')
                    ->counts('batches')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('cutoff_time')
                    ->label('Cutoff')
                    ->dateTime('M d, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        PickingWave::STATUS_DRAFT => 'Draft',
                        PickingWave::STATUS_RELEASED => 'Released',
                        PickingWave::STATUS_IN_PROGRESS => 'In Progress',
                        PickingWave::STATUS_COMPLETED => 'Completed',
                        PickingWave::STATUS_CANCELLED => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('release')
                    ->label('Release Wave')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (PickingWave $record) => $record->status === PickingWave::STATUS_DRAFT)
                    ->requiresConfirmation()
                    ->modalHeading('Release Wave & Generate Batches')
                    ->modalDescription('Releasing this wave will allocate unassigned lots via FEFO, partition orders into 4-tote carts, and calculate optimal S-Path routes.')
                    ->action(function (PickingWave $record, WavePickingService $service) {
                        try {
                            $service->releaseWave($record);
                            Notification::make()
                                ->title('Wave Released Successfully')
                                ->body("Wave {$record->wave_number} has generated batches and is ready for mobile scanner execution.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Release Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('view_route')
                    ->label('View Route')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->visible(fn (PickingWave $record) => in_array($record->status, [PickingWave::STATUS_RELEASED, PickingWave::STATUS_IN_PROGRESS, PickingWave::STATUS_COMPLETED]))
                    ->modalHeading(fn (PickingWave $record) => "Optimal S-Path Route: {$record->wave_number}")
                    ->modalContent(function (PickingWave $record) {
                        $batchItems = PickingBatchItem::whereHas('batch', fn ($q) => $q->where('picking_wave_id', $record->id))
                            ->with(['bin', 'item', 'materialLot', 'batch'])
                            ->orderBy('pick_sequence')
                            ->get();

                        return view('filament.modals.wave-route-view', [
                            'wave' => $record,
                            'batchItems' => $batchItems,
                        ]);
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PickingWave $record) => in_array($record->status, [PickingWave::STATUS_DRAFT, PickingWave::STATUS_RELEASED]))
                    ->requiresConfirmation()
                    ->action(function (PickingWave $record, WavePickingService $service) {
                        $service->cancelWave($record);
                        Notification::make()
                            ->title('Wave Cancelled')
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
            'index' => Pages\ListPickingWaves::route('/'),
            'create' => Pages\CreatePickingWave::route('/create'),
            'edit' => Pages\EditPickingWave::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CycleCountOrderResource\Pages;
use App\Filament\Resources\CycleCountOrderResource\RelationManagers\ItemsRelationManager;
use App\Models\CycleCountOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseZone;
use App\Services\CycleCountService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CycleCountOrderResource extends Resource
{
    protected static ?string $model = CycleCountOrder::class;

    protected static ?string $navigationGroup = 'Inventory & Audits';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Audit Order Scope')
                    ->schema([
                        Forms\Components\TextInput::make('count_number')
                            ->label('Count Number')
                            ->default(fn () => 'CC-'.date('Ym').'-'.rand(1000, 9999))
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->options(Warehouse::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('zone_id', null)),

                        Forms\Components\Select::make('zone_id')
                            ->label('Target Zone (Optional)')
                            ->options(function (callable $get) {
                                $warehouseId = $get('warehouse_id');
                                if (! $warehouseId) {
                                    return [];
                                }

                                return WarehouseZone::where('warehouse_id', $warehouseId)
                                    ->pluck('name', 'id');
                            })
                            ->placeholder('All Zones (Full Facility)')
                            ->nullable(),

                        Forms\Components\Select::make('count_type')
                            ->label('Count Methodology')
                            ->options([
                                CycleCountOrder::TYPE_BLIND => 'Blind Count (Hide Book Stock)',
                                CycleCountOrder::TYPE_ADHOC => 'Ad-hoc Spot Check',
                                CycleCountOrder::TYPE_SCHEDULED => 'Periodic Scheduled Count',
                            ])
                            ->default(CycleCountOrder::TYPE_BLIND)
                            ->required(),

                        Forms\Components\Select::make('assigned_to')
                            ->label('Assigned Counter')
                            ->options(User::pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->options([
                                CycleCountOrder::STATUS_DRAFT => 'Draft',
                                CycleCountOrder::STATUS_IN_PROGRESS => 'In Progress (Snapshot Active)',
                                CycleCountOrder::STATUS_COUNTED => 'Count Completed',
                                CycleCountOrder::STATUS_REVIEW => 'Supervisor Review Needed',
                                CycleCountOrder::STATUS_APPROVED => 'Approved',
                                CycleCountOrder::STATUS_POSTED => 'Posted to Ledger',
                                CycleCountOrder::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->default(CycleCountOrder::STATUS_DRAFT)
                            ->required()
                            ->disabled(fn ($context) => $context === 'create'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Instructions / Audit Notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('count_number')
                    ->label('Count Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Target Zone')
                    ->default('All Zones')
                    ->searchable(),

                Tables\Columns\TextColumn::make('count_type')
                    ->badge()
                    ->colors([
                        'primary' => CycleCountOrder::TYPE_BLIND,
                        'info' => CycleCountOrder::TYPE_SCHEDULED,
                        'gray' => CycleCountOrder::TYPE_ADHOC,
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => CycleCountOrder::STATUS_DRAFT,
                        'info' => CycleCountOrder::STATUS_IN_PROGRESS,
                        'warning' => CycleCountOrder::STATUS_COUNTED,
                        'danger' => CycleCountOrder::STATUS_REVIEW,
                        'success' => CycleCountOrder::STATUS_APPROVED,
                        'primary' => CycleCountOrder::STATUS_POSTED,
                    ]),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Counter')
                    ->placeholder('Unassigned')
                    ->searchable(),

                Tables\Columns\TextColumn::make('snapshot_at')
                    ->label('Snapshot At')
                    ->dateTime('M d, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('posted_at')
                    ->label('Posted At')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('startCount')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (CycleCountOrder $record) => $record->canStart())
                    ->requiresConfirmation()
                    ->action(function (CycleCountOrder $record, CycleCountService $service) {
                        $service->startCount($record);
                        Notification::make()
                            ->title('Count Initiated')
                            ->body('Balance snapshot frozen. Ready for blind scan entry.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('approveOrder')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (CycleCountOrder $record) => $record->canApprove())
                    ->requiresConfirmation()
                    ->action(function (CycleCountOrder $record, CycleCountService $service) {
                        $service->approveOrder($record, auth()->user() ?? User::first());
                        Notification::make()
                            ->title('Count Approved')
                            ->body("Order {$record->count_number} approved.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('postAdjustments')
                    ->label('Post Adjustments')
                    ->icon('heroicon-o-bolt')
                    ->color('primary')
                    ->visible(fn (CycleCountOrder $record) => $record->canPost())
                    ->requiresConfirmation()
                    ->modalDescription('This will commit double-entry ADJUSTMENT movements to the inventory ledger.')
                    ->action(function (CycleCountOrder $record, CycleCountService $service) {
                        $result = $service->postAdjustments($record, auth()->user() ?? User::first());
                        Notification::make()
                            ->title('Adjustments Committed')
                            ->body("Posted {$result['movements_recorded']} movements. Surplus: +{$result['total_surplus']} KG, Shortage: -{$result['total_shortage']} KG.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCycleCountOrders::route('/'),
            'create' => Pages\CreateCycleCountOrder::route('/create'),
            'view' => Pages\ViewCycleCountOrder::route('/{record}'),
            'edit' => Pages\EditCycleCountOrder::route('/{record}/edit'),
        ];
    }
}

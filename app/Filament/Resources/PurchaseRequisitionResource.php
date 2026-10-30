<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseRequisitionResource\Pages;
use App\Models\PurchaseRequisition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseRequisitionResource extends Resource
{
    protected static ?string $model = PurchaseRequisition::class;

    protected static ?string $navigationGroup = 'Bulk Storage & Silos';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Purchase Requisition Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('pr_number')
                            ->label('Requisition Number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'PR-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4))),
                        Forms\Components\Select::make('silo_id')
                            ->label('Origin Silo')
                            ->relationship('silo', 'code')
                            ->searchable()
                            ->nullable(),
                        Forms\Components\Select::make('item_id')
                            ->label('Polymer Item / SKU')
                            ->relationship('item', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('requested_quantity_kg')
                            ->label('Requested Quantity')
                            ->numeric()
                            ->suffix('kg')
                            ->required()
                            ->default(25000.00),
                        Forms\Components\Select::make('requested_by_type')
                            ->label('Requisition Source')
                            ->options([
                                PurchaseRequisition::REQUESTER_SYSTEM => 'System Automated Reorder',
                                PurchaseRequisition::REQUESTER_MANUAL => 'Manual Warehouse Operator',
                            ])
                            ->default(PurchaseRequisition::REQUESTER_MANUAL)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Approval Status')
                            ->options([
                                PurchaseRequisition::STATUS_DRAFT => 'Draft',
                                PurchaseRequisition::STATUS_SUBMITTED => 'Submitted',
                                PurchaseRequisition::STATUS_APPROVED => 'Approved',
                                PurchaseRequisition::STATUS_ORDERED => 'Ordered',
                                PurchaseRequisition::STATUS_FULFILLED => 'Fulfilled',
                                PurchaseRequisition::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->default(PurchaseRequisition::STATUS_DRAFT)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull()
                            ->placeholder('Procurement justification and notes...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pr_number')
                    ->label('PR Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('silo.code')
                    ->label('Silo')
                    ->badge()
                    ->color('gray')
                    ->placeholder('General'),
                Tables\Columns\TextColumn::make('item.sku')
                    ->label('Resin SKU')
                    ->badge()
                    ->color('info')
                    ->searchable(),
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Description')
                    ->limit(25),
                Tables\Columns\TextColumn::make('requested_quantity_kg')
                    ->label('Qty (kg)')
                    ->numeric(0)
                    ->suffix(' kg')
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_by_type')
                    ->label('Source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PurchaseRequisition::REQUESTER_SYSTEM => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PurchaseRequisition::STATUS_DRAFT => 'gray',
                        PurchaseRequisition::STATUS_SUBMITTED => 'info',
                        PurchaseRequisition::STATUS_APPROVED => 'success',
                        PurchaseRequisition::STATUS_ORDERED => 'warning',
                        PurchaseRequisition::STATUS_FULFILLED => 'success',
                        PurchaseRequisition::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PurchaseRequisition $record) => in_array($record->status, [PurchaseRequisition::STATUS_DRAFT, PurchaseRequisition::STATUS_SUBMITTED]))
                    ->action(function (PurchaseRequisition $record) {
                        $record->update([
                            'status' => PurchaseRequisition::STATUS_APPROVED,
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Purchase Requisition Approved')
                            ->body("PR {$record->pr_number} has been approved for vendor PO placement.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('mark_ordered')
                    ->label('Mark Ordered')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (PurchaseRequisition $record) => $record->status === PurchaseRequisition::STATUS_APPROVED)
                    ->action(function (PurchaseRequisition $record) {
                        $record->update(['status' => PurchaseRequisition::STATUS_ORDERED]);

                        Notification::make()
                            ->title('PO Placed')
                            ->body("PR {$record->pr_number} marked as Ordered.")
                            ->info()
                            ->send();
                    }),
                Tables\Actions\Action::make('mark_fulfilled')
                    ->label('Fulfill')
                    ->icon('heroicon-o-archive-box')
                    ->color('success')
                    ->visible(fn (PurchaseRequisition $record) => $record->status === PurchaseRequisition::STATUS_ORDERED)
                    ->action(function (PurchaseRequisition $record) {
                        $record->update([
                            'status' => PurchaseRequisition::STATUS_FULFILLED,
                            'fulfilled_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Requisition Fulfilled')
                            ->body("PR {$record->pr_number} marked as fulfilled upon tanker discharge.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseRequisitions::route('/'),
            'create' => Pages\CreatePurchaseRequisition::route('/create'),
            'edit' => Pages\EditPurchaseRequisition::route('/{record}/edit'),
        ];
    }
}

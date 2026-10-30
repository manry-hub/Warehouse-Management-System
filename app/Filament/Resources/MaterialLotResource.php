<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialLotResource\Pages;
use App\Models\MaterialLot;
use App\Models\User;
use App\Services\BarcodeLabelService;
use App\Services\LotQualityService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class MaterialLotResource extends Resource
{
    protected static ?string $model = MaterialLot::class;

    protected static ?string $navigationGroup = 'Inventory & Lots';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Lot Identification')
                    ->schema([
                        Forms\Components\TextInput::make('lot_number')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('item_id')
                            ->relationship('item', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('vendor_code'),
                        Forms\Components\TextInput::make('vendor_lot_number'),
                        Forms\Components\TextInput::make('vendor_heat_number'),
                    ])->columns(2),

                Forms\Components\Section::make('Dates & Status')
                    ->schema([
                        Forms\Components\DatePicker::make('manufactured_date'),
                        Forms\Components\DatePicker::make('expiry_date'),
                        Forms\Components\Select::make('status')
                            ->options([
                                MaterialLot::STATUS_QA_HOLD => 'QA Hold',
                                MaterialLot::STATUS_AVAILABLE => 'Available',
                                MaterialLot::STATUS_EXPIRED => 'Expired',
                                MaterialLot::STATUS_QUARANTINE => 'Quarantine',
                            ])
                            ->default(MaterialLot::STATUS_QA_HOLD)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Quality & COA')
                    ->schema([
                        Forms\Components\FileUpload::make('coa_file_path')
                            ->label('Certificate of Analysis (COA) PDF')
                            ->directory('coa_documents')
                            ->acceptedFileTypes(['application/pdf']),
                        Forms\Components\KeyValue::make('coa_metrics')
                            ->label('Inspection Parameters (Moisture %, MFI, Density)')
                            ->keyLabel('Parameter')
                            ->valueLabel('Value'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lot_number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('lot_type')
                    ->badge()
                    ->colors([
                        'primary' => MaterialLot::TYPE_VIRGIN,
                        'warning' => MaterialLot::TYPE_REGRIND,
                        'info' => MaterialLot::TYPE_MASTERBATCH,
                    ]),
                Tables\Columns\TextColumn::make('item.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('regrind_source_machine')
                    ->label('IMM Source')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vendor_heat_number')
                    ->label('Heat / Smelt #')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => MaterialLot::STATUS_QA_HOLD,
                        'success' => MaterialLot::STATUS_AVAILABLE,
                        'danger' => [MaterialLot::STATUS_EXPIRED, MaterialLot::STATUS_QUARANTINE],
                    ]),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lot_type')
                    ->options([
                        MaterialLot::TYPE_VIRGIN => 'Virgin Resin',
                        MaterialLot::TYPE_REGRIND => 'Recycled Regrind',
                        MaterialLot::TYPE_MASTERBATCH => 'Masterbatch',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        MaterialLot::STATUS_QA_HOLD => 'QA Hold',
                        MaterialLot::STATUS_AVAILABLE => 'Available',
                        MaterialLot::STATUS_QUARANTINE => 'Quarantine',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('releaseQa')
                    ->label('Approve QA')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (MaterialLot $record) => $record->status === MaterialLot::STATUS_QA_HOLD)
                    ->requiresConfirmation()
                    ->action(function (MaterialLot $record, LotQualityService $service) {
                        $service->releaseToAvailable($record, auth()->user() ?? User::first());
                        Notification::make()
                            ->title('Lot Approved for Dispatch')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('quarantine')
                    ->label('Quarantine')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection / Quarantine Reason')
                            ->required(),
                    ])
                    ->action(function (MaterialLot $record, array $data, LotQualityService $service) {
                        $service->quarantine($record, auth()->user() ?? User::first(), $data['reason']);
                        Notification::make()
                            ->title('Lot Quarantined')
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\Action::make('printLabel')
                    ->label('QR & ZPL')
                    ->icon('heroicon-o-qr-code')
                    ->modalHeading(fn (MaterialLot $record) => "Pallet Label: {$record->lot_number}")
                    ->modalContent(function (MaterialLot $record, BarcodeLabelService $labelService) {
                        $gs1 = $labelService->formatGs1String($record, 25.0);
                        $svg = $labelService->generateQrCodeSvg($gs1, 160);
                        $zpl = e($labelService->generateZplLabel($record, 'RM-DOCK', 25.0));

                        return new HtmlString("
                            <div style='text-align: center; margin-bottom: 15px;'>
                                {$svg}
                                <p style='font-family: monospace; font-size: 11px; margin-top: 8px;'>{$gs1}</p>
                            </div>
                            <div>
                                <label style='font-weight: bold; font-size: 12px;'>ZPL II Printer Payload (Port 9100):</label>
                                <pre style='background: #1e1e1e; color: #00ff66; padding: 10px; border-radius: 4px; font-size: 11px; max-height: 150px; overflow-y: auto;'>{$zpl}</pre>
                            </div>
                        ");
                    })
                    ->modalSubmitAction(false),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialLots::route('/'),
            'create' => Pages\CreateMaterialLot::route('/create'),
            'edit' => Pages\EditMaterialLot::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiloResource\Pages;
use App\Models\Item;
use App\Models\Silo;
use App\Models\SiloTelemetryReading;
use App\Services\SiloTelemetryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SiloResource extends Resource
{
    protected static ?string $model = Silo::class;

    protected static ?string $navigationGroup = 'Bulk Storage & Silos';

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Silo Identification & Warehouse Link')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Silo Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g. SILO-PP-01'),
                        Forms\Components\TextInput::make('name')
                            ->label('Silo Description')
                            ->required()
                            ->placeholder('e.g. Bulk PP Resin Silo 1'),
                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('zone_id')
                            ->relationship('zone', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('bin_id')
                            ->relationship('bin', 'bin_code')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('item_id')
                            ->relationship('item', 'name', fn ($query) => $query->where('category', Item::CATEGORY_RAW_RESIN))
                            ->required()
                            ->searchable(),
                    ]),

                Forms\Components\Section::make('Physical Geometry & Bulk Density')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('total_height_m')
                            ->label('Total Silo Height')
                            ->numeric()
                            ->suffix('m')
                            ->required()
                            ->default(12.00),
                        Forms\Components\TextInput::make('diameter_m')
                            ->label('Cylinder Diameter')
                            ->numeric()
                            ->suffix('m')
                            ->required()
                            ->default(3.50),
                        Forms\Components\TextInput::make('cone_height_m')
                            ->label('Conical Bottom Height')
                            ->numeric()
                            ->suffix('m')
                            ->default(2.00),
                        Forms\Components\TextInput::make('sensor_deadband_m')
                            ->label('Top Sensor Deadband')
                            ->numeric()
                            ->suffix('m')
                            ->default(0.50),
                        Forms\Components\TextInput::make('bulk_density_kg_m3')
                            ->label('Polymer Bulk Density')
                            ->numeric()
                            ->suffix('kg/m³')
                            ->required()
                            ->default(520.00),
                        Forms\Components\TextInput::make('max_capacity_kg')
                            ->label('Payload Capacity')
                            ->numeric()
                            ->suffix('kg')
                            ->required()
                            ->default(50000.00),
                    ]),

                Forms\Components\Section::make('Replenishment Thresholds & Status')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('reorder_threshold_kg')
                            ->label('Reorder Point')
                            ->numeric()
                            ->suffix('kg')
                            ->required()
                            ->default(12000.00),
                        Forms\Components\TextInput::make('critical_threshold_kg')
                            ->label('Critical Low Point')
                            ->numeric()
                            ->suffix('kg')
                            ->required()
                            ->default(5000.00),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Operational / In-Service')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Silo Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('item.sku')
                    ->label('Resin SKU')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('current_fill_percentage')
                    ->label('Fill %')
                    ->numeric(1)
                    ->suffix('%')
                    ->sortable()
                    ->color(fn (Silo $record): string => match ($record->status) {
                        Silo::STATUS_NORMAL => 'success',
                        Silo::STATUS_WARNING => 'warning',
                        Silo::STATUS_CRITICAL => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('current_weight_kg')
                    ->label('Stored Weight')
                    ->numeric(2)
                    ->suffix(' kg')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_capacity_kg')
                    ->label('Max Cap')
                    ->numeric(0)
                    ->suffix(' kg'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Silo::STATUS_NORMAL => 'success',
                        Silo::STATUS_WARNING => 'warning',
                        Silo::STATUS_CRITICAL => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('last_telemetry_at')
                    ->label('Last Telemetry')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('quick_ping')
                    ->label('Ping Telemetry')
                    ->icon('heroicon-o-signal')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('distance_m')
                            ->label('Ullage Distance (Meters)')
                            ->numeric()
                            ->required()
                            ->default(fn (Silo $record) => $record->current_distance_m ?? 4.0),
                    ])
                    ->action(function (Silo $record, array $data, SiloTelemetryService $telemetryService): void {
                        $result = $telemetryService->ingestReading(
                            silo: $record,
                            distanceMeters: (float) $data['distance_m'],
                            sensorType: SiloTelemetryReading::SENSOR_RADAR_80GHZ,
                            rawPayload: ['source' => 'filament_table_ping']
                        );

                        Notification::make()
                            ->title("Silo {$record->code} Reading Ingested")
                            ->body("Stored: {$result['metrics']['weight_kg']} kg ({$result['metrics']['fill_percentage']}%) - {$result['metrics']['status']}")
                            ->color($record->getStatusColor())
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
            'index' => Pages\ListSilos::route('/'),
            'create' => Pages\CreateSilo::route('/create'),
            'view' => Pages\ViewSilo::route('/{record}'),
            'edit' => Pages\EditSilo::route('/{record}/edit'),
        ];
    }
}

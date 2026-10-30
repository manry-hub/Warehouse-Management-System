<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseBinResource\Pages;
use App\Models\WarehouseBin;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseBinResource extends Resource
{
    protected static ?string $model = WarehouseBin::class;

    protected static ?string $navigationGroup = 'Warehouse Topology';

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('zone_id')
                    ->relationship('zone', 'name')
                    ->required(),
                Forms\Components\TextInput::make('bin_code')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('aisle')->required(),
                Forms\Components\TextInput::make('rack')->required(),
                Forms\Components\TextInput::make('shelf')->required(),
                Forms\Components\TextInput::make('bin')->required(),
                Forms\Components\TextInput::make('max_weight_kg')->numeric()->suffix('KG'),
                Forms\Components\TextInput::make('max_volume_cbm')->numeric()->suffix('m³'),
                Forms\Components\Toggle::make('is_blocked'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bin_code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('zone.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('zone.zone_type')
                    ->label('Zone Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('max_weight_kg')
                    ->label('Max Weight')
                    ->numeric(2)
                    ->suffix(' kg'),
                Tables\Columns\IconColumn::make('is_blocked')
                    ->boolean()
                    ->label('Blocked'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('zone_id')
                    ->relationship('zone', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouseBins::route('/'),
            'create' => Pages\CreateWarehouseBin::route('/create'),
            'edit' => Pages\EditWarehouseBin::route('/{record}/edit'),
        ];
    }
}

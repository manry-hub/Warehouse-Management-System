<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Models\Item;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationGroup = 'Inventory & Lots';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('sku')
                    ->label('SKU / Part Number')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('name')
                    ->label('Description')
                    ->required(),
                Forms\Components\Select::make('category')
                    ->options([
                        Item::CATEGORY_RAW_RESIN => 'Raw Resin',
                        Item::CATEGORY_MASTERBATCH => 'Masterbatch',
                        Item::CATEGORY_PACKAGING => 'Packaging',
                        Item::CATEGORY_FINISHED_GOOD => 'Finished Good',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('unit_of_measure')
                    ->label('UOM')
                    ->default('KG')
                    ->required(),
                Forms\Components\TextInput::make('standard_pack_qty')
                    ->numeric()
                    ->default(25.00),
                Forms\Components\TextInput::make('minimum_safety_stock')
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('shelf_life_days')
                    ->numeric()
                    ->suffix('Days'),
                Forms\Components\Section::make('Regrind Recycling Settings')
                    ->schema([
                        Forms\Components\Toggle::make('allow_regrind')
                            ->label('Allow Regrind Blending')
                            ->helperText('Permit recycled runner scrap to be blended into FEFO picklists.')
                            ->default(false)
                            ->reactive(),
                        Forms\Components\TextInput::make('max_regrind_percentage')
                            ->label('Max Regrind Ratio Cap (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(15.00)
                            ->visible(fn (callable $get) => (bool) $get('allow_regrind')),
                    ])->columns(2),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('Part SKU')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge(),
                Tables\Columns\TextColumn::make('unit_of_measure')
                    ->label('UOM'),
                Tables\Columns\TextColumn::make('standard_pack_qty')
                    ->label('Pack Size')
                    ->numeric(2),
                Tables\Columns\IconColumn::make('allow_regrind')
                    ->label('Regrind')
                    ->boolean(),
                Tables\Columns\TextColumn::make('max_regrind_percentage')
                    ->label('Regrind Cap')
                    ->formatStateUsing(fn ($record) => $record->allow_regrind ? "{$record->max_regrind_percentage}%" : '—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        Item::CATEGORY_RAW_RESIN => 'Raw Resin',
                        Item::CATEGORY_MASTERBATCH => 'Masterbatch',
                        Item::CATEGORY_PACKAGING => 'Packaging',
                        Item::CATEGORY_FINISHED_GOOD => 'Finished Good',
                    ]),
                Tables\Filters\TernaryFilter::make('allow_regrind')
                    ->label('Regrind Allowed'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}

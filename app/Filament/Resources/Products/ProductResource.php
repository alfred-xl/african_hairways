<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\OwnerOnly;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    use OwnerOnly;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Shop setup';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('description')->maxLength(255),
            MoneyInput::make('price_pence')->label('Price')->required(),
            ColorPicker::make('swatch_color')->label('Tile colour')->required()->default('#1b1f2a'),
            TextInput::make('sort_order')->label('Position in the list')->numeric()->integer()->minValue(0)->default(0)->required(),
            Toggle::make('is_active')->label('On sale')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ColorColumn::make('swatch_color')->label(''),
                TextColumn::make('name')->searchable(),
                TextColumn::make('description')->color('gray'),
                TextColumn::make('price_pence')->label('Price')->money('GBP', divideBy: 100)->sortable(),
                IconColumn::make('is_active')->label('On sale')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProducts::route('/'),
        ];
    }
}

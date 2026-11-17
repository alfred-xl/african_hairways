<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\ManageServices;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\OwnerOnly;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ServiceResource extends Resource
{
    use OwnerOnly;

    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    protected static string|UnitEnum|null $navigationGroup = 'Shop setup';

    protected static ?string $navigationLabel = 'Services & prices';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('service_category_id')->label('Category')->relationship('category', 'name')->required(),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('description')->maxLength(255)->columnSpanFull(),
            MoneyInput::make('price_pence')->label('Price')->required(),
            MoneyInput::make('walk_in_price_pence')->label('Walk-in price')->helperText('Optional. Charged when staff add the booking as a walk-in.'),
            TextInput::make('duration_minutes')->label('Length')->numeric()->integer()->minValue(5)->maxValue(600)->suffix('min')->required(),
            TextInput::make('badge')->maxLength(40)->helperText('Optional label such as "Best value". Also lists the service on the offers page.'),
            Toggle::make('available_as_addon')->label('Can be added as an extra'),
            Toggle::make('is_addon_only')->label('Extra only')->helperText("Can't be booked on its own."),
            Toggle::make('is_kids')->label("Kids' service")->helperText('Gets the kids midweek discount.'),
            Toggle::make('is_active')->label('Bookable')->default(true),
            TextInput::make('sort_order')->label('Position in the list')->numeric()->integer()->minValue(0)->default(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Service $record): ?string => $record->description),
                TextColumn::make('category.name')->label('Category')->badge()->color('gray'),
                TextColumn::make('price_pence')->label('Price')->money('GBP', divideBy: 100)->sortable(),
                TextColumn::make('walk_in_price_pence')->label('Walk-in')->money('GBP', divideBy: 100)->placeholder('—'),
                TextColumn::make('duration_minutes')->label('Length')->suffix(' min')->sortable(),
                IconColumn::make('available_as_addon')->label('Extra')->boolean(),
                IconColumn::make('is_active')->label('Bookable')->boolean(),
            ])
            ->filters([
                SelectFilter::make('service_category_id')->label('Category')->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('Past bookings keep their record of this service. To hide it without deleting, switch off "Bookable" instead.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServices::route('/'),
        ];
    }
}

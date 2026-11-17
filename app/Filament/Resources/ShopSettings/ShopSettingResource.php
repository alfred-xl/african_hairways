<?php

namespace App\Filament\Resources\ShopSettings;

use App\Filament\Resources\ShopSettings\Pages\ManageShopSettings;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\OwnerOnly;
use App\Models\ShopSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The shop has one settings row. It is edited here and never created or deleted.
 */
class ShopSettingResource extends Resource
{
    use OwnerOnly;

    protected static ?string $model = ShopSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Shop setup';

    protected static ?string $navigationLabel = 'Shop settings';

    protected static ?string $modelLabel = 'shop settings';

    protected static ?string $pluralModelLabel = 'shop settings';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('shop_name')->required()->maxLength(255),
            TextInput::make('tagline')->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(30),
            TextInput::make('address')->maxLength(255),
            TextInput::make('booking_window_days')->label('How far ahead customers can book')->numeric()->integer()->minValue(1)->maxValue(90)->suffix('days')->required(),
            Select::make('slot_step_minutes')->label('Offer a start time every')->options([5 => '5 minutes', 10 => '10 minutes', 15 => '15 minutes', 20 => '20 minutes', 30 => '30 minutes', 60 => '60 minutes'])->required(),
            TextInput::make('min_lead_minutes')->label('Shortest notice for an online booking')->numeric()->integer()->minValue(0)->maxValue(1440)->suffix('min')->required(),
            TextInput::make('second_visit_percent')->label('Every-second-visit discount')->numeric()->integer()->minValue(0)->maxValue(100)->suffix('%')->required()->helperText('0 switches it off.'),
            MoneyInput::make('kids_midweek_discount_pence')->label('Kids midweek discount')->required()->helperText('Off kids\' services, Tuesday to Thursday. 0 switches it off.'),
            Select::make('free_gift_product_id')->label('Free gift with product orders')->relationship('freeGiftProduct', 'name')->placeholder('No free gift'),
            MoneyInput::make('free_gift_threshold_pence')->label('Basket value that earns the gift')->required(),
            TextInput::make('reservation_hold_days')->label('Hold reserved products for')->numeric()->integer()->minValue(1)->maxValue(60)->suffix('days')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('shop_name')->description(fn (ShopSetting $record): string => collect([$record->address, $record->phone])->filter()->join(' · ')),
                TextColumn::make('booking_window_days')->label('Booking window')->suffix(' days'),
                TextColumn::make('second_visit_percent')->label('2nd visit discount')->suffix('%'),
            ])
            ->recordActions([
                EditAction::make()->label('Change settings')->modalHeading('Shop settings'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShopSettings::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\PromoCodes;

use App\Enums\PromoCodeType;
use App\Filament\Resources\PromoCodes\Pages\ManagePromoCodes;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\OwnerOnly;
use App\Filament\Support\TimeOptions;
use App\Models\PromoCode;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PromoCodeResource extends Resource
{
    use OwnerOnly;

    protected static ?string $model = PromoCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Shop setup';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->required()
                ->maxLength(30)
                ->alphaDash()
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (string $state): string => PromoCode::normaliseCode($state))
                ->helperText('Saved in capitals. Customers can type it in any case.'),
            TextInput::make('label')->required()->maxLength(255)->helperText('Shown on the booking summary, e.g. "Welcome 15% off".'),
            Select::make('type')->options(PromoCodeType::class)->required()->live()->default(PromoCodeType::Percent->value),
            TextInput::make('value')
                ->label('Percentage off')
                ->numeric()->integer()->minValue(1)->maxValue(100)->suffix('%')->required()
                ->visible(fn (Get $get): bool => self::typeOf($get) === PromoCodeType::Percent),
            MoneyInput::make('value')
                ->label('Amount off')
                ->minValue(0.01)->required()
                ->visible(fn (Get $get): bool => self::typeOf($get) === PromoCodeType::Fixed),
            TextInput::make('note')->maxLength(255)->helperText('Short reminder shown when the code is applied, e.g. "First visit only".'),
            TextInput::make('description')->label('Offers page text')->maxLength(255),
            Toggle::make('first_visit_only')->label('First visit only')->helperText('Refused for a mobile number with a completed visit.')->columnSpanFull(),
            CheckboxList::make('valid_weekdays')->label('Only for appointments on')->options(TimeOptions::weekdays())->columns(4)->helperText('Leave all unticked for any day.')->columnSpanFull(),
            Select::make('valid_before_time')->label('Only for appointments starting before')->options(TimeOptions::everyQuarterHour())->placeholder('Any time'),
            TextInput::make('max_redemptions')->label('Total uses allowed')->numeric()->integer()->minValue(1)->placeholder('Unlimited'),
            TextInput::make('max_redemptions_per_customer')->label('Uses per mobile number')->numeric()->integer()->minValue(1)->placeholder('Unlimited'),
            DateTimePicker::make('starts_at')->label('Valid from')->timezone(config('shop.timezone'))->seconds(false),
            DateTimePicker::make('expires_at')->label('Expires')->timezone(config('shop.timezone'))->seconds(false)->after('starts_at'),
            Toggle::make('is_active')->label('Active')->default(true),
            Toggle::make('show_on_offers_page')->label('Show on the offers page')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->weight('bold')->description(fn (PromoCode $record): string => $record->label),
                TextColumn::make('value')->label('Discount')->formatStateUsing(fn (PromoCode $record): string => $record->type === PromoCodeType::Percent
                    ? "{$record->value}% off"
                    : Money::format($record->value).' off'),
                TextColumn::make('redemptions_count')->label('Times used')->counts('countedRedemptions')
                    ->formatStateUsing(fn (PromoCode $record): string => $record->counted_redemptions_count.($record->max_redemptions ? " of {$record->max_redemptions}" : '')),
                TextColumn::make('expires_at')->label('Expires')->dateTime('j M Y, H:i', timezone: config('shop.timezone'))->placeholder('Never'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('Bookings that used this code keep their discount but lose the link to the code. To retire a code, switch it off instead.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePromoCodes::route('/'),
        ];
    }

    private static function typeOf(Get $get): ?PromoCodeType
    {
        $type = $get('type');

        return $type instanceof PromoCodeType ? $type : PromoCodeType::tryFrom((string) $type);
    }
}

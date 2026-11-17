<?php

namespace App\Filament\Resources\OpeningHours;

use App\Filament\Resources\OpeningHours\Pages\ManageOpeningHours;
use App\Filament\Support\OwnerOnly;
use App\Filament\Support\TimeOptions;
use App\Models\OpeningHour;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OpeningHourResource extends Resource
{
    use OwnerOnly;

    protected static ?string $model = OpeningHour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Shop setup';

    protected static ?string $navigationLabel = 'Opening hours';

    protected static ?string $modelLabel = 'opening hours';

    protected static ?string $pluralModelLabel = 'opening hours';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('is_closed')->label('Closed all day')->live()->columnSpanFull(),
            Select::make('opens_at')->label('Opens')->options(TimeOptions::everyQuarterHour())
                ->required(fn (Get $get): bool => ! $get('is_closed'))
                ->hidden(fn (Get $get): bool => (bool) $get('is_closed')),
            Select::make('closes_at')->label('Closes')->options(TimeOptions::everyQuarterHour())
                ->required(fn (Get $get): bool => ! $get('is_closed'))
                ->hidden(fn (Get $get): bool => (bool) $get('is_closed'))
                ->gt('opens_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Monday first, the way a UK week is read. Works on both MySQL and SQLite.
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByRaw('(day_of_week + 6) % 7'))
            ->paginated(false)
            ->columns([
                TextColumn::make('day_of_week')->label('Day')->formatStateUsing(fn (OpeningHour $record): string => $record->dayName()),
                TextColumn::make('opens_at')->label('Hours')->state(fn (OpeningHour $record): string => $record->isOpen()
                    ? substr($record->opens_at, 0, 5).' – '.substr($record->closes_at, 0, 5)
                    : 'Closed'),
            ])
            ->recordActions([
                EditAction::make()->modalHeading(fn (OpeningHour $record): string => $record->dayName().' opening hours'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOpeningHours::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\BlockedTimes;

use App\Enums\BlockedTimeType;
use App\Filament\Resources\BlockedTimes\Pages\ManageBlockedTimes;
use App\Models\BlockedTime;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Time when bookings can't be made: lunch, holidays, sick days and one-off closures.
 * Open to every signed-in member of staff.
 */
class BlockedTimeResource extends Resource
{
    protected static ?string $model = BlockedTime::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'Blocked time';

    protected static ?string $modelLabel = 'blocked time';

    protected static ?string $pluralModelLabel = 'blocked time';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('staff_member_id')
                ->label('Who is this for?')
                ->relationship('staffMember', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('sort_order'))
                ->placeholder('The whole shop (closure)')
                ->helperText('Leave empty to close the shop for everyone.'),
            Select::make('type')->options(BlockedTimeType::class)->required()->default(BlockedTimeType::Holiday->value),
            DateTimePicker::make('starts_at')->label('From')->timezone(config('shop.timezone'))->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label('Until')->timezone(config('shop.timezone'))->seconds(false)->required()->after('starts_at'),
            TextInput::make('reason')->label('Note')->maxLength(255)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $timezone = config('shop.timezone');

        return $table
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('staffMember.name')->label('Who')->placeholder('Whole shop'),
                TextColumn::make('type')->badge()->color('gray'),
                TextColumn::make('starts_at')->label('From')->dateTime('D j M Y, H:i', timezone: $timezone)->sortable(),
                TextColumn::make('ends_at')->label('Until')->dateTime('D j M Y, H:i', timezone: $timezone),
                TextColumn::make('reason')->label('Note')->placeholder('—'),
            ])
            ->filters([
                Filter::make('upcoming')
                    ->label('Hide past entries')
                    ->query(fn (Builder $query) => $query->where('ends_at', '>=', now()))
                    ->default(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBlockedTimes::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\StaffMembers;

use App\Filament\Resources\StaffMembers\Pages\ManageStaffMembers;
use App\Filament\Support\OwnerOnly;
use App\Filament\Support\TimeOptions;
use App\Models\StaffMember;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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

class StaffMemberResource extends Resource
{
    use OwnerOnly;

    protected static ?string $model = StaffMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Shop setup';

    protected static ?string $navigationLabel = 'Barbers & stylists';

    protected static ?string $modelLabel = 'barber or stylist';

    protected static ?string $pluralModelLabel = 'barbers & stylists';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('job_title')->required()->maxLength(255),
            ColorPicker::make('color')->label('Colour')->required()->default('#1d3557'),
            TextInput::make('sort_order')->label('Position in the list')->numeric()->integer()->minValue(0)->default(0)->required(),
            TagsInput::make('tags')->label('Specialities')->helperText('Shown on their card on the booking page.')->columnSpanFull(),
            Toggle::make('is_chair_renter')->label('Chair renter')->helperText('Customers pay them directly, so their bookings are left out of shop takings.'),
            Toggle::make('is_active')->label('Taking bookings')->default(true),
            CheckboxList::make('serviceCategories')
                ->label('Service categories they offer')
                ->relationship('serviceCategories', 'name')
                ->columns(2)
                ->columnSpanFull(),
            Repeater::make('workingHours')
                ->label('Working pattern')
                ->helperText('One row per working day. Leave a day out for a day off; add two rows for the same day for a split shift. Bookings are only offered inside the shop\'s opening hours.')
                ->relationship()
                ->schema([
                    Select::make('day_of_week')->label('Day')->options(TimeOptions::weekdays())->required(),
                    Select::make('starts_at')->label('From')->options(TimeOptions::everyQuarterHour())->required(),
                    Select::make('ends_at')->label('To')->options(TimeOptions::everyQuarterHour())->required()->gt('starts_at'),
                ])
                ->columns(3)
                ->orderColumn(false)
                ->addActionLabel('Add working day')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->searchable()->description(fn (StaffMember $record): string => $record->job_title),
                TextColumn::make('serviceCategories.name')->label('Offers')->badge()->color('gray'),
                IconColumn::make('is_chair_renter')->label('Chair renter')->boolean(),
                IconColumn::make('is_active')->label('Taking bookings')->boolean(),
            ])
            ->recordActions([
                // Staff are switched off rather than deleted, so their past bookings keep their name.
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStaffMembers::route('/'),
        ];
    }
}

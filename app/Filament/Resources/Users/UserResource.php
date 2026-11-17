<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\OwnerOnly;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    use OwnerOnly;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Shop setup';

    protected static ?string $navigationLabel = 'Staff logins';

    protected static ?string $modelLabel = 'staff login';

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('role')->options(UserRole::class)->required()->default(UserRole::Staff->value)
                // Owners can't demote themselves, so the shop is never left without an owner by accident.
                ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                ->helperText('Owners can change staff, services, prices, hours, products and promo codes. Staff work the diary and reservations.'),
            Select::make('staff_member_id')->label('Barber or stylist')->relationship('staffMember', 'name')->placeholder('Not linked'),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->rule(Password::min(10))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (User $record): string => $record->email),
                TextColumn::make('role')->badge()->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => $state === UserRole::Owner ? 'primary' : 'gray'),
                TextColumn::make('staffMember.name')->label('Barber or stylist')->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (User $record): bool => $record->is(auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}

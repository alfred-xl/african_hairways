<?php

namespace App\Filament\Resources\ProductReservations;

use App\Enums\ReservationStatus;
use App\Filament\Resources\ProductReservations\Pages\ManageProductReservations;
use App\Models\ProductReservation;
use App\Support\UkMobile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Click-and-collect reservations. Open to every signed-in member of staff; records are
 * made by customers on the shop page, so there is nothing to create or edit here.
 */
class ProductReservationResource extends Resource
{
    protected static ?string $model = ProductReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Product reservations';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['items', 'customer']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->searchable()->weight('bold'),
                TextColumn::make('customer_name')->label('Customer')->searchable()
                    ->description(fn (ProductReservation $record): string => UkMobile::display($record->customer->phone)),
                TextColumn::make('items')->label('Items')->state(fn (ProductReservation $record): string => $record->itemsSummary())->wrap(),
                TextColumn::make('total_pence')->label('To pay')->money('GBP', divideBy: 100),
                TextColumn::make('collect_by')->label('Hold until')->date('D j M'),
                TextColumn::make('status')->badge()->color(fn (ReservationStatus $state): string => match ($state) {
                    ReservationStatus::Reserved => 'warning',
                    ReservationStatus::Collected => 'success',
                    ReservationStatus::Cancelled => 'gray',
                }),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReservationStatus::class)->default(ReservationStatus::Reserved->value),
            ])
            ->recordActions([
                Action::make('collect')
                    ->label('Collected')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (ProductReservation $record): bool => $record->status === ReservationStatus::Reserved)
                    ->action(fn (ProductReservation $record) => $record->update(['status' => ReservationStatus::Collected, 'collected_at' => now()])),
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Use this when the customer no longer wants the products or the hold has run out.')
                    ->visible(fn (ProductReservation $record): bool => $record->status === ReservationStatus::Reserved)
                    ->action(fn (ProductReservation $record) => $record->update(['status' => ReservationStatus::Cancelled])),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProductReservations::route('/'),
        ];
    }
}

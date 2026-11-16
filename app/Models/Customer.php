<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['phone', 'name', 'email'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<ProductReservation, $this>
     */
    public function productReservations(): HasMany
    {
        return $this->hasMany(ProductReservation::class);
    }

    public function completedVisitsCount(): int
    {
        return $this->bookings()->where('status', BookingStatus::Completed)->count();
    }
}

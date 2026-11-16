<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingItem>
 */
class BookingItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'name' => 'Fade',
            'price_pence' => 2000,
            'duration_minutes' => 30,
            'is_addon' => false,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Booking;
use App\Models\BookingDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingDiscount>
 */
class BookingDiscountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'type' => DiscountType::SecondVisit,
            'label' => 'Every 2nd visit: 20% off',
            'amount_pence' => 400,
        ];
    }
}

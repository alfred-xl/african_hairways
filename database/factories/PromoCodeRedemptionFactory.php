<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCodeRedemption>
 */
class PromoCodeRedemptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promo_code_id' => PromoCode::factory(),
            'booking_id' => Booking::factory(),
            'customer_id' => Customer::factory(),
            'amount_pence' => 200,
        ];
    }
}

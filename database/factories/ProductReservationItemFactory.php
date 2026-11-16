<?php

namespace Database\Factories;

use App\Models\ProductReservation;
use App\Models\ProductReservationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReservationItem>
 */
class ProductReservationItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_reservation_id' => ProductReservation::factory(),
            'name' => 'Beard oil 30ml',
            'quantity' => 1,
            'unit_price_pence' => 1000,
            'is_free_gift' => false,
        ];
    }
}

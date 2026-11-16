<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\ProductReservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductReservation>
 */
class ProductReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'AH-'.mb_strtoupper(Str::random(6)),
            'customer_id' => Customer::factory(),
            'customer_name' => fake()->name(),
            'status' => ReservationStatus::Reserved,
            'total_pence' => 1000,
            'collect_by' => now()->addDays(7)->toDateString(),
        ];
    }

    public function collected(): static
    {
        return $this->state(['status' => ReservationStatus::Collected, 'collected_at' => now()]);
    }
}

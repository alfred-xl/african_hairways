<?php

namespace Database\Factories;

use App\Models\OpeningHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningHour>
 */
class OpeningHourFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->unique()->numberBetween(0, 6),
            'opens_at' => '09:00:00',
            'closes_at' => '19:00:00',
            'is_closed' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(['is_closed' => true]);
    }
}

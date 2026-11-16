<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_category_id' => ServiceCategory::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(4),
            'price_pence' => 2000,
            'duration_minutes' => 30,
            'is_addon_only' => false,
            'available_as_addon' => false,
            'is_kids' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function addon(): static
    {
        return $this->state(['is_addon_only' => true, 'available_as_addon' => true]);
    }

    public function kids(): static
    {
        return $this->state(['is_kids' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

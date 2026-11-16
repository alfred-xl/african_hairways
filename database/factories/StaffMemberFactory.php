<?php

namespace Database\Factories;

use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'job_title' => 'Barber',
            'color' => '#1d3557',
            'tags' => [],
            'is_chair_renter' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function chairRenter(): static
    {
        return $this->state(['is_chair_renter' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Models\StaffMember;
use App\Models\StaffWorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffWorkingHour>
 */
class StaffWorkingHourFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_member_id' => StaffMember::factory(),
            'day_of_week' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '19:00:00',
        ];
    }
}

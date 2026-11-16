<?php

namespace Database\Factories;

use App\Enums\BlockedTimeType;
use App\Models\BlockedTime;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockedTime>
 */
class BlockedTimeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_member_id' => StaffMember::factory(),
            'type' => BlockedTimeType::Other,
            'reason' => null,
            'starts_at' => now()->addDay()->setTime(12, 0),
            'ends_at' => now()->addDay()->setTime(13, 0),
        ];
    }

    public function shopWide(): static
    {
        return $this->state(['staff_member_id' => null, 'type' => BlockedTimeType::Closure]);
    }
}

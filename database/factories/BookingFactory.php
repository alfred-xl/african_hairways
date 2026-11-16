<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\StaffMember;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'AH-'.mb_strtoupper(Str::random(6)),
            'access_token' => Str::random(40),
            'staff_member_id' => StaffMember::factory(),
            'customer_id' => Customer::factory(),
            'customer_name' => fake()->name(),
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(10, 30),
            'duration_minutes' => 30,
            'status' => BookingStatus::Booked,
            'subtotal_pence' => 2000,
            'discount_pence' => 0,
            'total_pence' => 2000,
        ];
    }

    public function startingAt(CarbonInterface $startsAt, int $minutes = 30): static
    {
        return $this->state([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($minutes),
            'duration_minutes' => $minutes,
        ]);
    }

    public function completed(): static
    {
        return $this->state(['status' => BookingStatus::Completed, 'completed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);
    }

    public function noShow(): static
    {
        return $this->state(['status' => BookingStatus::NoShow]);
    }

    public function payStaffDirectly(): static
    {
        return $this->state(['pay_staff_directly' => true]);
    }
}

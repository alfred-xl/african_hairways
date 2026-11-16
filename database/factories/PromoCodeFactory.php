<?php

namespace Database\Factories;

use App\Enums\PromoCodeType;
use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('????##')),
            'label' => 'Test offer',
            'type' => PromoCodeType::Percent,
            'value' => 10,
            'first_visit_only' => false,
            'is_active' => true,
            'show_on_offers_page' => true,
        ];
    }

    public function percent(int $percent): static
    {
        return $this->state(['type' => PromoCodeType::Percent, 'value' => $percent]);
    }

    public function fixed(int $pence): static
    {
        return $this->state(['type' => PromoCodeType::Fixed, 'value' => $pence]);
    }

    public function firstVisitOnly(): static
    {
        return $this->state(['first_visit_only' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * The seeded student code's restriction: Monday to Thursday, before 3pm.
     */
    public function weekdaysBeforeThree(): static
    {
        return $this->state(['valid_weekdays' => [1, 2, 3, 4], 'valid_before_time' => '15:00:00']);
    }
}

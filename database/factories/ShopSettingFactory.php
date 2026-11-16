<?php

namespace Database\Factories;

use App\Models\ShopSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopSetting>
 */
class ShopSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_name' => 'African Hairways',
            'tagline' => 'Barbers · Braids · Locs',
            'phone' => '020 0000 0000',
            'address' => 'Your shop address, London',
        ];
    }
}

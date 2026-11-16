<?php

namespace Database\Seeders;

use App\Enums\PromoCodeType;
use App\Models\OpeningHour;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Opening hours per weekday (0 = Sunday ... 6 = Saturday).
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const OPENING_HOURS = [
        0 => ['10:00:00', '16:00:00'],
        1 => ['09:00:00', '19:00:00'],
        2 => ['09:00:00', '19:00:00'],
        3 => ['09:00:00', '19:00:00'],
        4 => ['09:00:00', '19:00:00'],
        5 => ['09:00:00', '19:00:00'],
        6 => ['09:00:00', '19:00:00'],
    ];

    private const BARBER_CATEGORIES = ['Haircuts', 'Beard & shave', 'Braids, twists & locs', 'Colour', 'Treatments'];

    /**
     * Seed the shop with the services, staff, products and offers from the original prototype.
     */
    public function run(): void
    {
        $categories = $this->seedCategories();

        $this->seedOpeningHours();
        $this->seedStaff($categories);
        $this->seedServices($categories);
        $this->seedPromoCodes();
        $this->seedProductsAndSettings();
    }

    /**
     * @return array<string, int> Category ids keyed by name.
     */
    private function seedCategories(): array
    {
        $names = ['Haircuts', 'Beard & shave', 'Hairdressing', 'Braids, twists & locs', 'Colour', 'Treatments'];

        $categories = [];

        foreach ($names as $position => $name) {
            $categories[$name] = ServiceCategory::updateOrCreate(['name' => $name], ['sort_order' => $position])->id;
        }

        return $categories;
    }

    private function seedOpeningHours(): void
    {
        foreach (self::OPENING_HOURS as $dayOfWeek => [$opensAt, $closesAt]) {
            OpeningHour::updateOrCreate(
                ['day_of_week' => $dayOfWeek],
                ['opens_at' => $opensAt, 'closes_at' => $closesAt, 'is_closed' => false],
            );
        }
    }

    /**
     * @param  array<string, int>  $categories
     */
    private function seedStaff(array $categories): void
    {
        $staff = [
            ['name' => 'Dami', 'job_title' => 'Senior barber', 'color' => '#b42318', 'day_off' => 1, 'tags' => ['Skin fades', '360 waves', 'Designs'], 'categories' => self::BARBER_CATEGORIES],
            ['name' => 'Kwame', 'job_title' => 'Locs & braids specialist', 'color' => '#1d3557', 'day_off' => 2, 'tags' => ['Retwists', 'Twists', 'Cornrows'], 'categories' => self::BARBER_CATEGORIES],
            ['name' => 'Jay', 'job_title' => 'Barber', 'color' => '#7a4b12', 'day_off' => 3, 'tags' => ['Beards', 'Hot towel shaves', 'Kids'], 'categories' => self::BARBER_CATEGORIES],
            ['name' => 'Amara', 'job_title' => 'Hairdresser (chair renter)', 'color' => '#7b2d6b', 'day_off' => 0, 'renter' => true, 'tags' => ['Silk press', 'Wigs & weaves', 'Knotless braids'], 'categories' => ['Hairdressing', 'Braids, twists & locs', 'Colour', 'Treatments']],
        ];

        foreach ($staff as $position => $person) {
            $staffMember = StaffMember::updateOrCreate(['name' => $person['name']], [
                'job_title' => $person['job_title'],
                'color' => $person['color'],
                'tags' => $person['tags'],
                'is_chair_renter' => $person['renter'] ?? false,
                'sort_order' => $position,
            ]);

            $staffMember->serviceCategories()->sync(
                array_map(fn (string $name): int => $categories[$name], $person['categories']),
            );

            // Each person works the shop's opening hours, apart from their weekly day off.
            $staffMember->workingHours()->delete();

            foreach (self::OPENING_HOURS as $dayOfWeek => [$opensAt, $closesAt]) {
                if ($dayOfWeek !== $person['day_off']) {
                    $staffMember->workingHours()->create([
                        'day_of_week' => $dayOfWeek,
                        'starts_at' => $opensAt,
                        'ends_at' => $closesAt,
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, int>  $categories
     */
    private function seedServices(array $categories): void
    {
        // [category, name, description, price in pounds, minutes, extra attributes]
        $services = [
            ['Haircuts', 'Simple cut', 'Clipper cut all over or a basic taper', 15, 25],
            ['Haircuts', 'Fade', 'Skin, low, mid or high fade', 18, 35],
            ['Haircuts', 'Fade with beard', 'Any fade plus beard trim and line-up', 20, 45],
            ['Haircuts', 'Detailed fade with treatments', 'Precision fade, razor line-up, hot towel and black spray finish', 25, 60, ['badge' => 'Best value']],
            ['Haircuts', 'Waves cut', 'Short cut brushed for 360 waves', 22, 35],
            ['Haircuts', 'Afro shape-up & trim', 'Scissor and clipper shaping', 25, 45],
            ['Haircuts', 'Shape-up with beard', 'Hairline, temples, neck and beard line', 10, 20],
            ['Haircuts', 'Kids cut (under 12)', 'Clipper cut or taper. £10 as a walk-in', 15, 30, ['is_kids' => true, 'walk_in_price_pence' => 1000]],
            ['Haircuts', 'Kids skin fade (under 12)', 'Skin fade for children', 18, 35, ['is_kids' => true]],
            ['Beard & shave', 'Facial shape-up with treatment', 'Beard and hairline shape-up with facial steam and hot towel', 15, 30],
            ['Beard & shave', 'Hot towel shave', 'Head or face, straight razor', 22, 40],
            ['Hairdressing', 'Wash & blow-dry', 'Cleanse, condition and blow-out', 30, 60],
            ['Hairdressing', 'Silk press', 'Wash, blow-dry and flat-iron finish', 55, 120],
            ['Hairdressing', "Ladies' cut & style", 'Trim or reshape, styled finish', 35, 60],
            ['Hairdressing', 'Relaxer retouch', 'Regrowth only, with treatment', 50, 90],
            ['Hairdressing', 'Sew-in weave', 'Braid-down and install, hair not included', 85, 150],
            ['Hairdressing', 'Frontal wig install', 'Customise, lay and style, wig not included', 75, 120],
            ['Hairdressing', 'Knotless braids (medium)', 'Mid-back length, hair included', 140, 300],
            ['Hairdressing', 'Deep conditioning treatment', 'Steam treatment for natural hair', 25, 40],
            ['Braids, twists & locs', 'Cornrows', 'Straight-back, up to 8 rows', 30, 60],
            ['Braids, twists & locs', 'Design cornrows', 'Patterns and curves', 45, 90],
            ['Braids, twists & locs', "Men's box braids", 'Medium size, shoulder length', 65, 150],
            ['Braids, twists & locs', 'Two-strand twists', 'Short to medium length', 40, 75],
            ['Braids, twists & locs', 'Starter locs', 'Comb coils or twists', 70, 120],
            ['Braids, twists & locs', 'Loc retwist', 'Palm-roll or interlock, up to 60 locs', 45, 75],
            ['Braids, twists & locs', 'Retwist & style', 'Retwist with barrels or a style', 55, 90],
            ['Colour', 'Hair dye', 'Full colour, black or fashion shades', 25, 45],
            ['Colour', 'Beard dye', 'Even colour for beard and moustache', 15, 20, ['available_as_addon' => true]],
            ['Treatments', 'Dandruff & scalp treatment', 'Exfoliating scrub, medicated wash, scalp massage', 15, 25, ['available_as_addon' => true]],
            ['Treatments', 'Wash & shampoo', 'Shampoo and conditioner', 8, 15, ['is_addon_only' => true, 'available_as_addon' => true]],
            ['Treatments', 'Hot towel', 'Add to any cut or beard trim', 5, 10, ['is_addon_only' => true, 'available_as_addon' => true]],
            ['Treatments', 'Facial steam', 'Opens pores, softens beard', 10, 15, ['is_addon_only' => true, 'available_as_addon' => true]],
            ['Treatments', 'Black spray enhancement', 'Sharpens hairline and beard colour', 5, 5, ['is_addon_only' => true, 'available_as_addon' => true]],
            ['Treatments', 'Hard part or design', 'Razor part or simple design', 5, 10, ['is_addon_only' => true, 'available_as_addon' => true]],
        ];

        foreach ($services as $position => $service) {
            [$category, $name, $description, $pounds, $minutes] = $service;

            Service::updateOrCreate(['name' => $name], [
                'service_category_id' => $categories[$category],
                'description' => $description,
                'price_pence' => $pounds * 100,
                'duration_minutes' => $minutes,
                'sort_order' => $position,
                ...($service[5] ?? []),
            ]);
        }
    }

    private function seedPromoCodes(): void
    {
        PromoCode::updateOrCreate(['code' => 'WELCOME15'], [
            'label' => 'Welcome 15% off',
            'note' => 'First visit only',
            'description' => '15% off your first appointment with any barber.',
            'type' => PromoCodeType::Percent,
            'value' => 15,
            'first_visit_only' => true,
            'max_redemptions_per_customer' => 1,
        ]);

        PromoCode::updateOrCreate(['code' => 'STUDENT10'], [
            'label' => 'Students 10% off',
            'note' => 'Mon–Thu before 3pm, show student ID',
            'description' => 'Monday to Thursday, appointments before 3pm. Bring your student ID.',
            'type' => PromoCodeType::Percent,
            'value' => 10,
            'valid_weekdays' => [1, 2, 3, 4],
            'valid_before_time' => '15:00:00',
        ]);

        PromoCode::updateOrCreate(['code' => 'FRIEND5'], [
            'label' => 'Refer a friend £5 off',
            'note' => 'Both of you get £5 off',
            'description' => 'Bring a friend and you both get £5 off your next cut.',
            'type' => PromoCodeType::Fixed,
            'value' => 500,
            'max_redemptions_per_customer' => 1,
        ]);
    }

    private function seedProductsAndSettings(): void
    {
        // [name, description, price in pounds, swatch colour]
        $products = [
            ['Beard oil 30ml', 'Argan & jojoba', 12, '#7a4b12'],
            ['Beard balm', 'Hold and shine', 10, '#5b3a1a'],
            ['Wave pomade', 'For 360 waves', 8, '#1d3557'],
            ['Edge control', 'Strong hold, no flakes', 7, '#3b3f4a'],
            ['Wave brush', 'Medium boar bristle', 10, '#6b4423'],
            ['Curl sponge', 'Twists and coils', 6, '#b42318'],
            ['Silky durag', 'Keeps waves locked', 6, '#2d2d2d'],
            ['Anti-dandruff shampoo', 'Tea tree, 250ml', 10, '#1f7a4d'],
            ['Leave-in conditioner', 'Shea butter, 250ml', 9, '#9a6700'],
            ['Loc & twist gel', 'Aloe based, no build-up', 9, '#5a3e8a'],
            ['Black hair fibres', 'Fills thin spots', 12, '#111111'],
        ];

        foreach ($products as $position => [$name, $description, $pounds, $color]) {
            Product::updateOrCreate(['name' => $name], [
                'description' => $description,
                'price_pence' => $pounds * 100,
                'swatch_color' => $color,
                'sort_order' => $position,
            ]);
        }

        $settings = ShopSetting::current();

        $settings->fill([
            'shop_name' => 'African Hairways',
            'tagline' => 'Barbers · Braids · Locs',
            'phone' => '020 0000 0000',
            'address' => 'Your shop address, London',
            'booking_window_days' => 14,
            'slot_step_minutes' => 15,
            'min_lead_minutes' => 15,
            'second_visit_percent' => 20,
            'kids_midweek_discount_pence' => 300,
            'free_gift_threshold_pence' => 2000,
            'free_gift_product_id' => Product::where('name', 'Silky durag')->value('id'),
            'reservation_hold_days' => 7,
        ])->save();
    }
}

<?php

use App\Data\NewBooking;
use App\Enums\BookingSource;
use App\Models\OpeningHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Shop fixtures
|--------------------------------------------------------------------------
|
| Tests run against a small, predictable shop: open 09:00 to 19:00 every day,
| with "now" pinned to a Monday morning in British Summer Time so that the
| difference between London time and UTC is always exercised.
|
*/

/**
 * A moment in the shop's timezone, e.g. london('2026-06-02 10:00').
 */
function london(string $time): CarbonImmutable
{
    return CarbonImmutable::parse($time, 'Europe/London');
}

/**
 * Open the shop 09:00-19:00 seven days a week and pin "now" to Monday 1 June 2026, 08:00 London time.
 */
function openShop(string $now = '2026-06-01 08:00'): ShopSetting
{
    test()->travelTo(london($now));

    foreach (range(0, 6) as $dayOfWeek) {
        OpeningHour::factory()->create(['day_of_week' => $dayOfWeek]);
    }

    return ShopSetting::factory()->create();
}

/**
 * A staff member who works the shop's full hours every day.
 */
function staffMember(array $attributes = []): StaffMember
{
    $staffMember = StaffMember::factory()->create($attributes);

    foreach (range(0, 6) as $dayOfWeek) {
        $staffMember->workingHours()->create(['day_of_week' => $dayOfWeek, 'starts_at' => '09:00:00', 'ends_at' => '19:00:00']);
    }

    return $staffMember;
}

/**
 * A service in a category the staff member offers. Pass a factory state name to shape it, e.g. 'addon' or 'kids'.
 */
function serviceFor(StaffMember $staffMember, array $attributes = [], ?string $state = null): Service
{
    $category = $staffMember->serviceCategories()->first() ?? ServiceCategory::factory()->create();
    $staffMember->serviceCategories()->syncWithoutDetaching($category);

    $factory = Service::factory()->for($category, 'category');

    return ($state ? $factory->{$state}() : $factory)->create($attributes);
}

/**
 * The details of a booking request, with sensible defaults for whatever a test doesn't care about.
 */
function bookingRequest(StaffMember $staffMember, Service $service, string $startsAt, array $overrides = []): NewBooking
{
    return new NewBooking(...[
        'staffMemberId' => $staffMember->id,
        'serviceId' => $service->id,
        'extraServiceIds' => [],
        'startsAt' => london($startsAt),
        'customerName' => 'Tobi A.',
        'phone' => '+447700900123',
        'source' => BookingSource::Online,
        ...$overrides,
    ]);
}

<?php

use App\Enums\BookingStatus;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\OpeningHour;
use App\Models\ShopSetting;
use App\Services\AvailabilityService;

/**
 * Slot start times for a day, as "HH:MM" strings in London time.
 *
 * @return list<string>
 */
function slotTimes($staffMember, string $date, int $minutes = 30): array
{
    return array_map(
        fn ($slot) => $slot->format('H:i'),
        app(AvailabilityService::class)->slots($staffMember, london($date), $minutes),
    );
}

it('offers a time every 15 minutes from opening until the last appointment that fits before closing', function () {
    openShop();
    $staffMember = staffMember();

    $times = slotTimes($staffMember, '2026-06-02', minutes: 30);

    expect($times)->toHaveCount(39)
        ->and($times[0])->toBe('09:00')
        ->and($times[1])->toBe('09:15')
        ->and(end($times))->toBe('18:30');
});

it('stops offering times earlier for a longer appointment', function () {
    openShop();
    $staffMember = staffMember();

    $times = slotTimes($staffMember, '2026-06-02', minutes: 300);

    expect(end($times))->toBe('14:00');
});

it('hides every time that would overlap an existing booking', function () {
    openShop();
    $staffMember = staffMember();
    Booking::factory()->for($staffMember)->startingAt(london('2026-06-02 10:00'), 30)->create();

    $times = slotTimes($staffMember, '2026-06-02', minutes: 30);

    expect($times)->toContain('09:30', '10:30')
        ->not->toContain('09:45', '10:00', '10:15');
});

it('keeps a completed booking blocked', function () {
    openShop();
    $staffMember = staffMember();
    Booking::factory()->for($staffMember)->completed()->startingAt(london('2026-06-02 10:00'), 30)->create();

    expect(slotTimes($staffMember, '2026-06-02'))->not->toContain('10:00');
});

it('frees the time of a booking that is no longer happening', function (BookingStatus $status) {
    openShop();
    $staffMember = staffMember();
    Booking::factory()->for($staffMember)->startingAt(london('2026-06-02 10:00'), 30)->create(['status' => $status]);

    expect(slotTimes($staffMember, '2026-06-02'))->toContain('10:00');
})->with([
    'cancelled' => BookingStatus::Cancelled,
    'no-show' => BookingStatus::NoShow,
]);

it('ignores bookings for other staff members', function () {
    openShop();
    $staffMember = staffMember();
    Booking::factory()->for(staffMember())->startingAt(london('2026-06-02 10:00'), 30)->create();

    expect(slotTimes($staffMember, '2026-06-02'))->toContain('10:00');
});

it('hides times inside a staff member\'s blocked time', function () {
    openShop();
    $staffMember = staffMember();
    BlockedTime::factory()->for($staffMember)->create([
        'starts_at' => london('2026-06-02 13:00'),
        'ends_at' => london('2026-06-02 14:00'),
    ]);

    $times = slotTimes($staffMember, '2026-06-02', minutes: 30);

    expect($times)->toContain('12:30', '14:00')
        ->not->toContain('12:45', '13:00', '13:45');
});

it('hides times for everyone during a whole-shop closure', function () {
    openShop();
    $staffMember = staffMember();
    BlockedTime::factory()->shopWide()->create([
        'starts_at' => london('2026-06-02 00:00'),
        'ends_at' => london('2026-06-03 00:00'),
    ]);

    expect(slotTimes($staffMember, '2026-06-02'))->toBe([])
        ->and(slotTimes($staffMember, '2026-06-03'))->not->toBe([]);
});

it('offers nothing on a day the staff member does not work', function () {
    openShop();
    $staffMember = staffMember();
    $staffMember->workingHours()->where('day_of_week', 2)->delete();

    expect(slotTimes($staffMember, '2026-06-02'))->toBe([]);
});

it('offers nothing on a day the shop is closed', function () {
    openShop();
    $staffMember = staffMember();
    OpeningHour::where('day_of_week', 2)->update(['is_closed' => true]);

    expect(slotTimes($staffMember, '2026-06-02'))->toBe([]);
});

it('only offers times inside both the opening hours and the working pattern', function () {
    openShop();
    $staffMember = staffMember();
    $staffMember->workingHours()->where('day_of_week', 2)->update(['starts_at' => '07:00:00', 'ends_at' => '12:00:00']);

    $times = slotTimes($staffMember, '2026-06-02', minutes: 30);

    expect($times[0])->toBe('09:00')
        ->and(end($times))->toBe('11:30');
});

it('keeps appointments out of the gap in a split shift', function () {
    openShop();
    $staffMember = staffMember();
    $staffMember->workingHours()->where('day_of_week', 2)->update(['ends_at' => '13:00:00']);
    $staffMember->workingHours()->create(['day_of_week' => 2, 'starts_at' => '14:00:00', 'ends_at' => '19:00:00']);

    $times = slotTimes($staffMember, '2026-06-02', minutes: 30);

    expect($times)->toContain('12:30', '14:00')
        ->not->toContain('12:45', '13:00', '13:30');
});

it('offers nothing that starts within 15 minutes of now', function (string $now, string $firstTime) {
    openShop($now);
    $staffMember = staffMember();

    expect(slotTimes($staffMember, '2026-06-01')[0])->toBe($firstTime);
})->with([
    'exactly 15 minutes ahead is allowed' => ['2026-06-01 10:00', '10:15'],
    'one minute later pushes to the next slot' => ['2026-06-01 10:01', '10:30'],
    'mid-slot' => ['2026-06-01 10:07', '10:30'],
]);

it('offers nothing in the past', function () {
    openShop('2026-06-01 12:00');
    $staffMember = staffMember();

    expect(slotTimes($staffMember, '2026-05-31'))->toBe([])
        ->and(slotTimes($staffMember, '2026-06-01'))->not->toContain('09:00', '11:45', '12:00');
});

it('offers times for 14 days including today and nothing after', function () {
    openShop();
    $staffMember = staffMember();

    expect(slotTimes($staffMember, '2026-06-14'))->not->toBe([])
        ->and(slotTimes($staffMember, '2026-06-15'))->toBe([]);
});

it('follows the booking window and slot step set by the owner', function () {
    openShop();
    ShopSetting::query()->update(['booking_window_days' => 3, 'slot_step_minutes' => 30]);
    $staffMember = staffMember();

    expect(slotTimes($staffMember, '2026-06-03'))->toContain('09:30')->not->toContain('09:15')
        ->and(slotTimes($staffMember, '2026-06-04'))->toBe([]);
});

it('compares London appointment times with bookings stored in UTC', function () {
    openShop();
    $staffMember = staffMember();
    Booking::factory()->for($staffMember)->create([
        'starts_at' => '2026-06-02 09:00:00', // 10:00 in London during British Summer Time
        'ends_at' => '2026-06-02 09:30:00',
    ]);

    expect(slotTimes($staffMember, '2026-06-02'))->toContain('09:00')->not->toContain('10:00');
});

it('lists the days in the booking window and flags the ones the staff member works', function () {
    openShop();
    $staffMember = staffMember();
    $staffMember->workingHours()->where('day_of_week', 2)->delete();
    BlockedTime::factory()->for($staffMember)->create([
        'starts_at' => london('2026-06-04 00:00'),
        'ends_at' => london('2026-06-05 00:00'),
    ]);

    $days = collect(app(AvailabilityService::class)->bookableDays($staffMember))
        ->mapWithKeys(fn (array $day) => [$day['date']->format('Y-m-d') => $day['working']]);

    expect($days)->toHaveCount(14)
        ->and($days['2026-06-01'])->toBeTrue()
        ->and($days['2026-06-02'])->toBeFalse()   // weekly day off
        ->and($days['2026-06-04'])->toBeFalse()   // holiday
        ->and($days['2026-06-14'])->toBeTrue();
});

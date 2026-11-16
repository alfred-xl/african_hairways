<?php

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Exceptions\SlotUnavailableException;
use App\Mail\BookingConfirmed;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Support\Facades\Mail;

function book(...$arguments): Booking
{
    return app(BookingService::class)->create(bookingRequest(...$arguments));
}

it('creates a booking priced from the database with its line items', function () {
    openShop();
    $staffMember = staffMember();
    $fade = serviceFor($staffMember, ['name' => 'Fade', 'price_pence' => 1800, 'duration_minutes' => 35]);
    $hotTowel = serviceFor($staffMember, ['name' => 'Hot towel', 'price_pence' => 500, 'duration_minutes' => 10], 'addon');

    $booking = book($staffMember, $fade, '2026-06-02 10:00', ['extraServiceIds' => [$hotTowel->id], 'notes' => 'Skin fade']);

    expect($booking->status)->toBe(BookingStatus::Booked)
        ->and($booking->subtotal_pence)->toBe(2300)
        ->and($booking->total_pence)->toBe(2300)
        ->and($booking->duration_minutes)->toBe(45)
        ->and($booking->localStartsAt()->format('Y-m-d H:i'))->toBe('2026-06-02 10:00')
        ->and($booking->localEndsAt()->format('H:i'))->toBe('10:45')
        ->and($booking->starts_at->format('H:i'))->toBe('09:00') // stored in UTC
        ->and($booking->notes)->toBe('Skin fade')
        ->and($booking->items->pluck('name')->all())->toBe(['Fade', 'Hot towel'])
        ->and($booking->customer->phone)->toBe('+447700900123')
        ->and($booking->reference)->toStartWith('AH-')
        ->and(strlen($booking->access_token))->toBe(40);
});

it('refuses a booking that overlaps another for the same staff member', function (string $secondStart) {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['duration_minutes' => 60]);
    book($staffMember, $service, '2026-06-02 10:00');

    expect(fn () => book($staffMember, $service, $secondStart, ['phone' => '+447700900456']))
        ->toThrow(SlotUnavailableException::class);

    expect(Booking::count())->toBe(1);
})->with([
    'the same start time' => '2026-06-02 10:00',
    'starting during the first booking' => '2026-06-02 10:45',
    'ending during the first booking' => '2026-06-02 09:15',
]);

it('counts extras towards the length when checking for overlaps', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['duration_minutes' => 30]);
    $extra = serviceFor($staffMember, ['duration_minutes' => 15], 'addon');
    book($staffMember, $service, '2026-06-02 10:00', ['extraServiceIds' => [$extra->id]]);

    expect(fn () => book($staffMember, $service, '2026-06-02 10:30', ['phone' => '+447700900456']))
        ->toThrow(SlotUnavailableException::class);

    expect(book($staffMember, $service, '2026-06-02 10:45', ['phone' => '+447700900456']))->toBeInstanceOf(Booking::class);
});

it('refuses a long booking that would swallow a shorter one', function () {
    openShop();
    $staffMember = staffMember();
    $short = serviceFor($staffMember, ['duration_minutes' => 30]);
    $long = serviceFor($staffMember, ['duration_minutes' => 120]);
    book($staffMember, $short, '2026-06-02 10:30');

    expect(fn () => book($staffMember, $long, '2026-06-02 10:00', ['phone' => '+447700900456']))
        ->toThrow(SlotUnavailableException::class);
});

it('allows bookings that sit back to back', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['duration_minutes' => 30]);
    book($staffMember, $service, '2026-06-02 10:00');

    book($staffMember, $service, '2026-06-02 10:30', ['phone' => '+447700900456']);
    book($staffMember, $service, '2026-06-02 09:30', ['phone' => '+447700900789']);

    expect(Booking::count())->toBe(3);
});

it('allows the same time with a different staff member', function () {
    openShop();
    $first = staffMember();
    $second = staffMember();
    $service = serviceFor($first);
    $second->serviceCategories()->attach($service->service_category_id);
    book($first, $service, '2026-06-02 10:00');

    $booking = book($second, $service, '2026-06-02 10:00', ['phone' => '+447700900456']);

    expect($booking->staff_member_id)->toBe($second->id);
});

it('lets a cancelled booking\'s time be booked again', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    $first = book($staffMember, $service, '2026-06-02 10:00');
    app(BookingService::class)->cancel($first, 'customer');

    $second = book($staffMember, $service, '2026-06-02 10:00', ['phone' => '+447700900456']);

    expect($second->status)->toBe(BookingStatus::Booked);
});

it('refuses times a customer is not allowed to book', function (string $startsAt) {
    openShop('2026-06-01 12:00');
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['duration_minutes' => 30]);

    expect(fn () => book($staffMember, $service, $startsAt))->toThrow(SlotUnavailableException::class);

    expect(Booking::count())->toBe(0);
})->with([
    'in the past' => '2026-06-01 09:00',
    'within 15 minutes of now' => '2026-06-01 12:00',
    'before opening' => '2026-06-02 08:30',
    'running past closing' => '2026-06-02 18:45',
    'off the 15 minute grid' => '2026-06-02 10:05',
    'beyond the 14 day window' => '2026-06-15 10:00',
]);

it('refuses a time inside blocked time', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    BlockedTime::factory()->for($staffMember)->create([
        'starts_at' => london('2026-06-02 12:00'),
        'ends_at' => london('2026-06-02 13:00'),
    ]);

    expect(fn () => book($staffMember, $service, '2026-06-02 12:15'))->toThrow(SlotUnavailableException::class);
});

it('refuses a service from a category the staff member does not offer', function () {
    openShop();
    $staffMember = staffMember();
    $otherService = Service::factory()->create();

    expect(fn () => book($staffMember, $otherService, '2026-06-02 10:00'))->toThrow(BookingException::class);

    expect(Booking::count())->toBe(0);
});

it('refuses an extra from a category the staff member does not offer', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    $otherExtra = Service::factory()->addon()->create();

    expect(fn () => book($staffMember, $service, '2026-06-02 10:00', ['extraServiceIds' => [$otherExtra->id]]))
        ->toThrow(BookingException::class);
});

it('refuses an add-on booked as the main service', function () {
    openShop();
    $staffMember = staffMember();
    $addon = serviceFor($staffMember, state: 'addon');

    expect(fn () => book($staffMember, $addon, '2026-06-02 10:00'))->toThrow(BookingException::class);
});

it('refuses a main service passed off as an extra', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    $notAnExtra = serviceFor($staffMember);

    expect(fn () => book($staffMember, $service, '2026-06-02 10:00', ['extraServiceIds' => [$notAnExtra->id]]))
        ->toThrow(BookingException::class);
});

it('refuses services and staff that have been switched off', function () {
    openShop();
    $staffMember = staffMember();
    $inactiveService = serviceFor($staffMember, state: 'inactive');
    $inactiveStaff = staffMember(['is_active' => false]);
    $service = serviceFor($inactiveStaff);

    expect(fn () => book($staffMember, $inactiveService, '2026-06-02 10:00'))->toThrow(BookingException::class)
        ->and(fn () => book($inactiveStaff, $service, '2026-06-02 10:00'))->toThrow(BookingException::class);
});

it('reuses one customer record however the mobile number was typed', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);

    book($staffMember, $service, '2026-06-02 10:00', ['phone' => '+447700900123']);
    book($staffMember, $service, '2026-06-03 10:00', ['phone' => '+447700900123', 'customerName' => 'Tobi Adeyemi']);

    expect(Customer::count())->toBe(1)
        ->and(Customer::first()->name)->toBe('Tobi Adeyemi');
});

it('stops one mobile number holding more than five upcoming online bookings', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    foreach (range(2, 6) as $day) {
        book($staffMember, $service, "2026-06-0{$day} 10:00");
    }

    expect(fn () => book($staffMember, $service, '2026-06-07 10:00'))->toThrow(BookingException::class);
});

it('marks bookings with a chair renter as paid to them directly', function () {
    openShop();
    $renter = staffMember(['is_chair_renter' => true]);
    $service = serviceFor($renter);

    expect(book($renter, $service, '2026-06-02 10:00')->pay_staff_directly)->toBeTrue();
});

it('lets staff add a walk-in outside the public booking rules', function () {
    openShop('2026-06-01 12:03');
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['price_pence' => 1500, 'walk_in_price_pence' => 1000]);
    $user = User::factory()->create();

    $booking = book($staffMember, $service, '2026-06-01 12:05', ['source' => BookingSource::WalkIn, 'createdBy' => $user]);

    expect($booking->source)->toBe(BookingSource::WalkIn)
        ->and($booking->total_pence)->toBe(1000)
        ->and($booking->created_by_user_id)->toBe($user->id);
});

it('still refuses a staff-made booking that overlaps another', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['duration_minutes' => 30]);
    book($staffMember, $service, '2026-06-02 10:00');

    expect(fn () => book($staffMember, $service, '2026-06-02 10:10', ['source' => BookingSource::Phone, 'phone' => '+447700900456']))
        ->toThrow(SlotUnavailableException::class);
});

it('emails the confirmation when an address is given', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    Mail::fake();

    $booking = book($staffMember, $service, '2026-06-02 10:00', ['email' => 'tobi@example.com']);

    Mail::assertSent(BookingConfirmed::class, fn (BookingConfirmed $mail) => $mail->hasTo('tobi@example.com') && $mail->booking->is($booking));
});

it('sends no email when no address is given', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    Mail::fake();

    book($staffMember, $service, '2026-06-02 10:00');

    Mail::assertNothingSent();
});

it('marks a booking done, cancelled or no-show', function (string $method, array $arguments, BookingStatus $expected) {
    $booking = Booking::factory()->create();

    app(BookingService::class)->{$method}($booking, ...$arguments);

    expect($booking->fresh()->status)->toBe($expected);
})->with([
    'done' => ['complete', [], BookingStatus::Completed],
    'cancelled' => ['cancel', ['staff'], BookingStatus::Cancelled],
    'no-show' => ['markNoShow', [], BookingStatus::NoShow],
]);

it('refuses to change a booking that is already finished', function () {
    $booking = Booking::factory()->completed()->create();

    expect(fn () => app(BookingService::class)->cancel($booking, 'staff'))->toThrow(BookingException::class);

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed);
});

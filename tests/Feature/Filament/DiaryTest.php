<?php

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Filament\Pages\Diary;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Services\BookingService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('shows the chosen day\'s bookings with customer details to signed-in staff', function () {
    openShop();
    $staffMember = staffMember(['name' => 'Dami']);
    $customer = Customer::factory()->create(['phone' => '+447700900123']);
    Booking::factory()->for($staffMember)->for($customer)->startingAt(london('2026-06-01 10:00'), 30)
        ->create(['customer_name' => 'Tobi A.', 'notes' => 'Skin fade']);
    Booking::factory()->for($staffMember)->startingAt(london('2026-06-02 10:00'), 30)
        ->create(['customer_name' => 'Tomorrow Person']);
    actingAs(User::factory()->create());

    Livewire::test(Diary::class)
        ->assertSee('Dami')
        ->assertSee('10:00 – 10:30')
        ->assertSee('Tobi A.')
        ->assertSee('07700 900123')
        ->assertSee('Skin fade')
        ->assertDontSee('Tomorrow Person');
});

it('moves between days', function () {
    openShop();
    $staffMember = staffMember();
    Booking::factory()->for($staffMember)->startingAt(london('2026-06-02 10:00'), 30)->create(['customer_name' => 'Tomorrow Person']);
    actingAs(User::factory()->create());

    Livewire::test(Diary::class)
        ->call('nextDay')
        ->assertSet('date', '2026-06-02')
        ->assertSee('Tomorrow Person')
        ->call('previousDay')
        ->assertSet('date', '2026-06-01')
        ->assertDontSee('Tomorrow Person');
});

it('falls back to today when the date in the address is not a date', function () {
    openShop();
    actingAs(User::factory()->create());

    Livewire::withQueryParams(['date' => 'nonsense'])->test(Diary::class)->assertSet('date', '2026-06-01');
});

it('leaves chair renters, cancellations and no-shows out of expected takings', function () {
    openShop();
    $barber = staffMember();
    $renter = staffMember(['is_chair_renter' => true]);
    $at = fn (string $time) => london("2026-06-01 {$time}");
    Booking::factory()->for($barber)->startingAt($at('10:00'))->create(['total_pence' => 1800]);
    Booking::factory()->for($barber)->completed()->startingAt($at('11:00'))->create(['total_pence' => 2500]);
    Booking::factory()->for($barber)->cancelled()->startingAt($at('12:00'))->create(['total_pence' => 7000]);
    Booking::factory()->for($barber)->noShow()->startingAt($at('13:00'))->create(['total_pence' => 9000]);
    Booking::factory()->for($renter)->payStaffDirectly()->startingAt($at('10:00'))->create(['total_pence' => 5500]);
    actingAs(User::factory()->create());

    Livewire::test(Diary::class)
        ->assertViewHas('stats', ['appointments' => 3, 'completed' => 1, 'takingsPence' => 4300])
        ->assertSee('£43');
});

it('shows blocked time and whole-shop closures in each column', function () {
    openShop();
    $staffMember = staffMember();
    BlockedTime::factory()->for($staffMember)->create([
        'starts_at' => london('2026-06-01 13:00'), 'ends_at' => london('2026-06-01 14:00'), 'reason' => 'Dentist',
    ]);
    actingAs(User::factory()->create());

    Livewire::test(Diary::class)->assertSee('13:00 – 14:00')->assertSee('Dentist');
});

it('changes a booking\'s status from the diary', function (string $action, BookingStatus $expected) {
    openShop();
    $booking = Booking::factory()->for(staffMember())->startingAt(london('2026-06-01 10:00'))->create();
    actingAs(User::factory()->create());

    Livewire::test(Diary::class)
        ->callAction(TestAction::make($action)->arguments(['booking' => $booking->id]))
        ->assertNotified();

    expect($booking->fresh()->status)->toBe($expected);
})->with([
    'mark done' => ['complete', BookingStatus::Completed],
    'no-show' => ['noShow', BookingStatus::NoShow],
    'cancel' => ['cancel', BookingStatus::Cancelled],
]);

it('counts a visit marked done towards the customer\'s next discount', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['price_pence' => 2000]);
    $first = app(BookingService::class)->create(bookingRequest($staffMember, $service, '2026-06-01 10:00'));
    actingAs(User::factory()->create());

    Livewire::test(Diary::class)->callAction(TestAction::make('complete')->arguments(['booking' => $first->id]));

    $second = app(BookingService::class)->create(bookingRequest($staffMember, $service, '2026-06-03 10:00'));
    expect($second->total_pence)->toBe(1600);
});

it('adds a walk-in booking by hand', function () {
    openShop('2026-06-01 12:03');
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['price_pence' => 1500, 'walk_in_price_pence' => 1000, 'duration_minutes' => 30]);
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(Diary::class)
        ->callAction('addBooking', [
            'staff_member_id' => $staffMember->id,
            'service_id' => $service->id,
            'extra_service_ids' => [],
            'starts_at' => '2026-06-01 12:05',
            'source' => BookingSource::WalkIn->value,
            'customer_name' => 'Walk-in Wale',
            'phone' => '07700 900555',
        ])
        ->assertHasNoFormErrors()
        ->assertSee('Walk-in Wale');

    $booking = Booking::sole();
    expect($booking->source)->toBe(BookingSource::WalkIn)
        ->and($booking->total_pence)->toBe(1000)
        ->and($booking->localStartsAt()->format('Y-m-d H:i'))->toBe('2026-06-01 12:05')
        ->and($booking->created_by_user_id)->toBe($user->id)
        ->and($booking->customer->phone)->toBe('+447700900555');
});

it('refuses a hand-made booking that overlaps another', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['duration_minutes' => 30]);
    Booking::factory()->for($staffMember)->startingAt(london('2026-06-01 12:00'), 30)->create();
    actingAs(User::factory()->create());

    Livewire::test(Diary::class)
        ->callAction('addBooking', [
            'staff_member_id' => $staffMember->id,
            'service_id' => $service->id,
            'starts_at' => '2026-06-01 12:15',
            'source' => BookingSource::Phone->value,
            'customer_name' => 'Phone Femi',
            'phone' => '07700 900555',
        ])
        ->assertNotified("Booking wasn't added");

    expect(Booking::count())->toBe(1);
});

it('asks for a UK mobile number on a hand-made booking', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    actingAs(User::factory()->create());

    Livewire::test(Diary::class)
        ->callAction('addBooking', [
            'staff_member_id' => $staffMember->id,
            'service_id' => $service->id,
            'starts_at' => '2026-06-01 12:15',
            'source' => BookingSource::Phone->value,
            'customer_name' => 'Phone Femi',
            'phone' => '12345',
        ])
        ->assertHasFormErrors(['phone']);

    expect(Booking::count())->toBe(0);
});

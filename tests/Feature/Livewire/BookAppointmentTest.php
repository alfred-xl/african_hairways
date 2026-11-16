<?php

use App\Livewire\BookAppointment;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PromoCode;
use App\Models\Service;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/**
 * The booking page with a staff member, service, day and time already chosen.
 */
function bookingPage($staffMember, $service, string $date = '2026-06-02', string $time = '10:00')
{
    return Livewire::test(BookAppointment::class)
        ->call('selectStaff', $staffMember->id)
        ->set('serviceId', $service->id)
        ->call('selectDate', $date)
        ->call('selectTime', $time);
}

it('shows the booking page with staff, services and free times', function () {
    openShop();
    $staffMember = staffMember(['name' => 'Dami']);
    serviceFor($staffMember, ['name' => 'Skin fade', 'price_pence' => 1800]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Book an appointment')
        ->assertSee('Dami')
        ->assertSee('Skin fade')
        ->assertSee('£18')
        ->assertSee('09:00');
});

it('books an appointment and sends the customer to their private link', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['price_pence' => 1800, 'duration_minutes' => 30]);
    $extra = serviceFor($staffMember, ['price_pence' => 500, 'duration_minutes' => 10], 'addon');

    $page = bookingPage($staffMember, $service)
        ->set('extraIds', [$extra->id])
        ->set('name', 'Tobi A.')
        ->set('phone', '07700 900123')
        ->set('notes', 'Keep some length on top')
        ->call('confirm');

    $booking = Booking::sole();
    $page->assertHasNoErrors()->assertRedirect(route('bookings.show', $booking->access_token));
    expect($booking->customer_name)->toBe('Tobi A.')
        ->and($booking->customer->phone)->toBe('+447700900123')
        ->and($booking->total_pence)->toBe(2300)
        ->and($booking->duration_minutes)->toBe(40)
        ->and($booking->localStartsAt()->format('Y-m-d H:i'))->toBe('2026-06-02 10:00')
        ->and($booking->notes)->toBe('Keep some length on top');
});

it('asks for a name and a UK mobile number before booking', function (string $name, string $phone, string $field) {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);

    bookingPage($staffMember, $service)
        ->set('name', $name)
        ->set('phone', $phone)
        ->call('confirm')
        ->assertHasErrors($field);

    expect(Booking::count())->toBe(0);
})->with([
    'no name' => ['', '07700900123', 'name'],
    'no number' => ['Tobi', '', 'phone'],
    'landline' => ['Tobi', '020 7946 0000', 'phone'],
    'too few digits' => ['Tobi', '0770090', 'phone'],
]);

it('rejects an email address that is not valid', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);

    bookingPage($staffMember, $service)
        ->set('name', 'Tobi')
        ->set('phone', '07700900123')
        ->set('email', 'not-an-email')
        ->call('confirm')
        ->assertHasErrors('email');

    expect(Booking::count())->toBe(0);
});

it('will not confirm without a chosen time', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);

    Livewire::test(BookAppointment::class)
        ->set('name', 'Tobi')
        ->set('phone', '07700900123')
        ->call('confirm')
        ->assertHasErrors('booking');

    expect(Booking::count())->toBe(0);
});

it('throws away a service the chosen staff member does not offer', function () {
    openShop();
    $staffMember = staffMember();
    $offered = serviceFor($staffMember);
    $notOffered = Service::factory()->create();

    Livewire::test(BookAppointment::class)
        ->set('serviceId', $notOffered->id)
        ->assertSet('serviceId', $offered->id);
});

it('throws away a time that is not on offer', function (string $time) {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    Booking::factory()->for($staffMember)->startingAt(london('2026-06-02 11:00'), 30)->create();

    bookingPage($staffMember, $service, time: $time)->assertSet('time', null);
})->with([
    'already booked' => '11:00',
    'before opening' => '07:00',
    'off the grid' => '10:05',
    'not a time' => 'soon',
]);

it('throws away a date outside the booking window', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);

    Livewire::test(BookAppointment::class)
        ->call('selectDate', '2026-07-30')
        ->assertSet('date', '2026-06-01');
});

it('tells the customer when someone else takes the time first', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    $page = bookingPage($staffMember, $service)->set('name', 'Tobi')->set('phone', '07700900123');
    Booking::factory()->for($staffMember)->startingAt(london('2026-06-02 10:00'), 30)->create();

    $page->call('confirm')
        ->assertHasErrors('booking')
        ->assertSet('time', null)
        ->assertSee('Someone just took that time');

    expect(Booking::count())->toBe(1);
});

it('never puts other customers\' details on the public page', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    $customer = Customer::factory()->create(['phone' => '+447700900999', 'name' => 'Zebedee Private']);
    Booking::factory()->for($staffMember)->for($customer)->startingAt(london('2026-06-02 11:00'), 30)
        ->create(['customer_name' => 'Zebedee Private', 'notes' => 'Secret note', 'email' => 'zeb@example.com']);

    $html = bookingPage($staffMember, $service)->html();

    expect($html)->not->toContain('Zebedee')
        ->not->toContain('7700900999')
        ->not->toContain('Secret note')
        ->not->toContain('zeb@example.com');
});

it('does not reveal a returning customer\'s discount until the booking is confirmed', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['price_pence' => 2000]);
    $customer = Customer::factory()->create(['phone' => '+447700900123']);
    Booking::factory()->for($customer)->completed()->startingAt(london('2026-05-01 10:00'))->create();

    $page = bookingPage($staffMember, $service)->set('name', 'Tobi')->set('phone', '07700900123');

    expect($page->instance()->quote->totalPence)->toBe(2000);
    $page->assertDontSee('Every 2nd visit: 20% off');

    $page->call('confirm');

    expect(Booking::latest('id')->first()->total_pence)->toBe(1600);
});

it('shows a promo code\'s discount in the summary once applied', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['price_pence' => 2000]);
    PromoCode::factory()->percent(10)->create(['code' => 'TENOFF', 'label' => 'Ten percent off']);

    $page = bookingPage($staffMember, $service)->set('promoInput', 'tenoff')->call('applyPromo');

    $page->assertSet('promoCode', 'TENOFF')->assertSee('Ten percent off applied');
    expect($page->instance()->quote->totalPence)->toBe(1800);
});

it('refuses a promo code that does not exist', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);

    bookingPage($staffMember, $service)
        ->set('promoInput', 'NOPE')
        ->call('applyPromo')
        ->assertHasErrors('promoInput')
        ->assertSet('promoCode', null);
});

it('warns when the applied code does not fit the chosen time and charges full price', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['price_pence' => 2000]);
    PromoCode::factory()->percent(10)->weekdaysBeforeThree()->create(['code' => 'STUDENT10']);

    $page = bookingPage($staffMember, $service, date: '2026-06-02', time: '10:00')
        ->set('promoInput', 'STUDENT10')
        ->call('applyPromo')
        ->call('selectDate', '2026-06-05') // a Friday
        ->call('selectTime', '10:00');

    $page->assertSee('STUDENT10 only works Monday to Thursday before 3pm');
    expect($page->instance()->quote->totalPence)->toBe(2000);
});

it('stops taking bookings from one connection after ten in ten minutes', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    foreach (range(1, 10) as $attempt) {
        RateLimiter::hit('book:127.0.0.1', 600);
    }

    bookingPage($staffMember, $service)
        ->set('name', 'Tobi')
        ->set('phone', '07700900123')
        ->call('confirm')
        ->assertHasErrors('booking');

    expect(Booking::count())->toBe(0);
});

it('limits how often promo codes can be tried', function () {
    openShop();
    $staffMember = staffMember();
    $service = serviceFor($staffMember);
    PromoCode::factory()->create(['code' => 'REAL']);
    foreach (range(1, 10) as $attempt) {
        RateLimiter::hit('promo:127.0.0.1', 60);
    }

    bookingPage($staffMember, $service)
        ->set('promoInput', 'REAL')
        ->call('applyPromo')
        ->assertHasErrors('promoInput')
        ->assertSet('promoCode', null);
});

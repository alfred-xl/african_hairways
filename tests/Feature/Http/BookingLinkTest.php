<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\PromoCode;

it('shows a booking to whoever has its private link', function () {
    $booking = Booking::factory()->startingAt(london('2026-06-02 10:00'))->create(['customer_name' => 'Tobi A.', 'total_pence' => 1800]);
    BookingItem::factory()->for($booking)->create(['name' => 'Skin fade', 'price_pence' => 1800]);
    $this->travelTo(london('2026-06-01 08:00'));

    $this->get(route('bookings.show', $booking->access_token))
        ->assertOk()
        ->assertSee("You're booked", escape: false)
        ->assertSee('Tobi A.')
        ->assertSee('Tuesday 2 June')
        ->assertSee('10:00')
        ->assertSee('Skin fade')
        ->assertSee($booking->reference)
        ->assertSee('noindex');
});

it('returns 404 for a link that matches no booking', function () {
    Booking::factory()->create();

    $this->get('/booking/'.str_repeat('a', 40))->assertNotFound();
});

it('does not find a booking by its id or reference', function () {
    $booking = Booking::factory()->create();

    $this->get("/booking/{$booking->id}")->assertNotFound();
    $this->get("/booking/{$booking->reference}")->assertNotFound();
});

it('lets the customer cancel an upcoming booking from the link', function () {
    $booking = Booking::factory()->startingAt(london('2026-06-02 10:00'))->create();
    $this->travelTo(london('2026-06-01 08:00'));

    $this->post(route('bookings.cancel', $booking->access_token))
        ->assertRedirect(route('bookings.show', $booking->access_token));

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_by)->toBe('customer');
    $this->get(route('bookings.show', $booking->access_token))->assertOk()->assertSee('Booking cancelled');
});

it('will not cancel a booking once its start time has passed', function () {
    $booking = Booking::factory()->startingAt(london('2026-06-02 10:00'))->create();
    $this->travelTo(london('2026-06-02 10:05'));

    $this->post(route('bookings.cancel', $booking->access_token))->assertSessionHas('booking_error');

    expect($booking->fresh()->status)->toBe(BookingStatus::Booked);
});

it('will not cancel a booking that is already finished', function () {
    $booking = Booking::factory()->completed()->startingAt(london('2026-06-02 10:00'))->create();
    $this->travelTo(london('2026-06-01 08:00'));

    $this->post(route('bookings.cancel', $booking->access_token))->assertSessionHas('booking_error');

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed);
});

it('shows the price list with walk-in prices and add-ons', function () {
    openShop();
    $staffMember = staffMember();
    serviceFor($staffMember, ['name' => 'Kids cut', 'price_pence' => 1500, 'walk_in_price_pence' => 1000]);
    serviceFor($staffMember, ['name' => 'Hot towel', 'price_pence' => 500], 'addon');
    serviceFor($staffMember, ['name' => 'Retired cut'], 'inactive');

    $this->get('/prices')
        ->assertOk()
        ->assertSee('Kids cut')
        ->assertSee('Walk-in £10')
        ->assertSee('+£5')
        ->assertDontSee('Retired cut');
});

it('lists current offers and leaves out switched-off promo codes', function () {
    openShop();
    PromoCode::factory()->create(['code' => 'WELCOME15', 'label' => 'Welcome 15% off']);
    PromoCode::factory()->inactive()->create(['code' => 'OLDCODE']);

    $this->get('/offers')
        ->assertOk()
        ->assertSee('WELCOME15')
        ->assertSee('20% off every 2nd visit')
        ->assertDontSee('OLDCODE');
});

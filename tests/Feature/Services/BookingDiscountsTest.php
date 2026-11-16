<?php

use App\Enums\DiscountType;
use App\Exceptions\PromoCodeRejectedException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Services\BookingService;

/**
 * A customer with the test mobile number and a number of completed visits.
 */
function customerWithVisits(int $completedVisits): Customer
{
    $customer = Customer::factory()->create(['phone' => '+447700900123']);

    Booking::factory()->count($completedVisits)->for($customer)->completed()
        ->startingAt(london('2026-05-01 10:00'))->create();

    return $customer;
}

function bookWithCode(?string $code, string $startsAt = '2026-06-02 10:00', array $overrides = []): Booking
{
    $staffMember = staffMember();
    $service = serviceFor($staffMember, ['price_pence' => 2000]);

    return app(BookingService::class)->create(
        bookingRequest($staffMember, $service, $startsAt, ['promoCode' => $code, ...$overrides]),
    );
}

describe('every second visit', function () {
    it('gives 20% off when the customer has an odd number of completed visits', function (int $completedVisits, int $expectedTotal) {
        openShop();
        customerWithVisits($completedVisits);

        $booking = bookWithCode(null);

        expect($booking->total_pence)->toBe($expectedTotal);
    })->with([
        'first visit: full price' => [0, 2000],
        'second visit: 20% off' => [1, 1600],
        'third visit: full price' => [2, 2000],
        'fourth visit: 20% off' => [3, 1600],
    ]);

    it('records the discount against the booking', function () {
        openShop();
        customerWithVisits(1);

        $booking = bookWithCode(null);

        expect($booking->discount_pence)->toBe(400)
            ->and($booking->discounts)->toHaveCount(1)
            ->and($booking->discounts->first()->type)->toBe(DiscountType::SecondVisit);
    });

    it('does not count cancelled or missed bookings as visits', function () {
        openShop();
        $customer = customerWithVisits(0);
        Booking::factory()->for($customer)->cancelled()->startingAt(london('2026-05-01 10:00'))->create();
        Booking::factory()->for($customer)->noShow()->startingAt(london('2026-05-02 10:00'))->create();

        expect(bookWithCode(null)->total_pence)->toBe(2000);
    });

    it('gives the discount to only one upcoming booking at a time', function () {
        openShop();
        customerWithVisits(1);

        $first = bookWithCode(null, '2026-06-02 10:00');
        $second = bookWithCode(null, '2026-06-03 10:00');

        expect($first->total_pence)->toBe(1600)
            ->and($second->total_pence)->toBe(2000);
    });

    it('wins over a promo code, which is left unused', function () {
        openShop();
        customerWithVisits(1);
        PromoCode::factory()->percent(50)->create(['code' => 'HALF']);

        $booking = bookWithCode('HALF');

        expect($booking->total_pence)->toBe(1600)
            ->and($booking->promo_code_id)->toBeNull()
            ->and(PromoCodeRedemption::count())->toBe(0);
    });
});

describe('promo codes', function () {
    it('applies a valid code and records the redemption', function () {
        openShop();
        $promoCode = PromoCode::factory()->percent(10)->create(['code' => 'TENOFF']);

        $booking = bookWithCode('tenoff ');

        expect($booking->total_pence)->toBe(1800)
            ->and($booking->promo_code_id)->toBe($promoCode->id)
            ->and($booking->promoCodeRedemption->amount_pence)->toBe(200)
            ->and($booking->promoCodeRedemption->customer_id)->toBe($booking->customer_id);
    });

    it('refuses the booking when the code cannot be used', function (Closure $makeCode) {
        openShop();
        $makeCode();

        expect(fn () => bookWithCode('SAVE'))->toThrow(PromoCodeRejectedException::class);

        expect(Booking::count())->toBe(0);
    })->with([
        'unknown code' => fn () => null,
        'switched off' => fn () => PromoCode::factory()->inactive()->create(['code' => 'SAVE']),
        'expired' => fn () => PromoCode::factory()->create(['code' => 'SAVE', 'expires_at' => london('2026-05-31 23:59')]),
        'not started yet' => fn () => PromoCode::factory()->create(['code' => 'SAVE', 'starts_at' => london('2026-07-01 00:00')]),
    ]);

    it('accepts a code up to its expiry date', function () {
        openShop();
        PromoCode::factory()->percent(10)->create(['code' => 'SAVE', 'expires_at' => london('2026-06-01 23:59')]);

        expect(bookWithCode('SAVE')->total_pence)->toBe(1800);
    });

    it('refuses a first-visit-only code for a number that has visited before', function () {
        openShop();
        customerWithVisits(2);
        PromoCode::factory()->percent(15)->firstVisitOnly()->create(['code' => 'WELCOME15']);

        expect(fn () => bookWithCode('WELCOME15'))->toThrow(PromoCodeRejectedException::class, 'first visits only');

        expect(Booking::where('status', 'booked')->count())->toBe(0);
    });

    it('accepts a first-visit-only code for a new number', function () {
        openShop();
        PromoCode::factory()->percent(15)->firstVisitOnly()->create(['code' => 'WELCOME15']);

        expect(bookWithCode('WELCOME15')->total_pence)->toBe(1700);
    });

    it('accepts a first-visit-only code for a number whose earlier bookings never happened', function () {
        openShop();
        $customer = customerWithVisits(0);
        Booking::factory()->for($customer)->cancelled()->startingAt(london('2026-05-01 10:00'))->create();
        PromoCode::factory()->percent(15)->firstVisitOnly()->create(['code' => 'WELCOME15']);

        expect(bookWithCode('WELCOME15')->total_pence)->toBe(1700);
    });

    it('only accepts the student code Monday to Thursday before 3pm', function (string $startsAt, bool $accepted) {
        openShop();
        PromoCode::factory()->percent(10)->weekdaysBeforeThree()->create(['code' => 'STUDENT10']);

        if ($accepted) {
            expect(bookWithCode('STUDENT10', $startsAt)->total_pence)->toBe(1800);
        } else {
            expect(fn () => bookWithCode('STUDENT10', $startsAt))
                ->toThrow(PromoCodeRejectedException::class, 'STUDENT10 only works Monday to Thursday before 3pm');
        }
    })->with([
        'Monday morning' => ['2026-06-08 09:00', true],
        'Thursday 2:45pm' => ['2026-06-04 14:45', true],
        'Thursday 3pm' => ['2026-06-04 15:00', false],
        'Tuesday evening' => ['2026-06-02 18:00', false],
        'Friday morning' => ['2026-06-05 10:00', false],
        'Sunday morning' => ['2026-06-07 10:00', false],
    ]);

    it('refuses a code once its usage limit is reached', function () {
        openShop();
        PromoCode::factory()->percent(10)->create(['code' => 'LIMITED', 'max_redemptions' => 1]);
        bookWithCode('LIMITED', '2026-06-02 10:00');

        expect(fn () => bookWithCode('LIMITED', '2026-06-02 11:00', ['phone' => '+447700900456']))
            ->toThrow(PromoCodeRejectedException::class, 'used up');
    });

    it('gives a use back when the booking that used it is cancelled', function () {
        openShop();
        PromoCode::factory()->percent(10)->create(['code' => 'LIMITED', 'max_redemptions' => 1]);
        $first = bookWithCode('LIMITED', '2026-06-02 10:00');
        app(BookingService::class)->cancel($first, 'customer');

        $second = bookWithCode('LIMITED', '2026-06-02 11:00', ['phone' => '+447700900456']);

        expect($second->total_pence)->toBe(1800);
    });

    it('refuses a code the same mobile number has already used up', function () {
        openShop();
        PromoCode::factory()->fixed(500)->create(['code' => 'FRIEND5', 'max_redemptions_per_customer' => 1]);
        bookWithCode('FRIEND5', '2026-06-02 10:00');

        expect(fn () => bookWithCode('FRIEND5', '2026-06-03 10:00'))
            ->toThrow(PromoCodeRejectedException::class, 'already been used');

        expect(bookWithCode('FRIEND5', '2026-06-03 10:00', ['phone' => '+447700900456'])->total_pence)->toBe(1500);
    });

    it('takes the kids midweek discount first, then the code', function () {
        openShop();
        $staffMember = staffMember();
        $kidsCut = serviceFor($staffMember, ['price_pence' => 1500], 'kids');
        PromoCode::factory()->percent(10)->create(['code' => 'TENOFF']);

        $booking = app(BookingService::class)->create(
            bookingRequest($staffMember, $kidsCut, '2026-06-03 10:00', ['promoCode' => 'TENOFF']),
        );

        // £15 - £3 = £12, then 10% off = £10.80
        expect($booking->total_pence)->toBe(1080)
            ->and($booking->discounts->pluck('type')->all())->toBe([DiscountType::KidsMidweek, DiscountType::PromoCode]);
    });
});

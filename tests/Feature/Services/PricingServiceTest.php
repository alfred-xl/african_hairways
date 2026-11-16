<?php

use App\Enums\BookingSource;
use App\Enums\DiscountType;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Services\PricingService;

function quoteFor(Service $main, array $arguments = [])
{
    return app(PricingService::class)->quote(...[
        'main' => $main,
        'extras' => [],
        'startsAt' => london('2026-06-01 10:00'), // a Monday
        'settings' => ShopSetting::factory()->make(),
        ...$arguments,
    ]);
}

it('adds the main service and extras into the subtotal and time in the chair', function () {
    $main = Service::factory()->create(['price_pence' => 1800, 'duration_minutes' => 35]);
    $extras = [
        Service::factory()->addon()->create(['price_pence' => 500, 'duration_minutes' => 10]),
        Service::factory()->addon()->create(['price_pence' => 800, 'duration_minutes' => 15]),
    ];

    $quote = quoteFor($main, ['extras' => $extras]);

    expect($quote->subtotalPence)->toBe(3100)
        ->and($quote->totalPence)->toBe(3100)
        ->and($quote->durationMinutes)->toBe(60)
        ->and($quote->discounts)->toBe([]);
});

it('takes the kids midweek amount off a kids cut from Tuesday to Thursday', function (string $date, int $expectedTotal) {
    $main = Service::factory()->kids()->create(['price_pence' => 1500]);

    $quote = quoteFor($main, ['startsAt' => london("{$date} 10:00")]);

    expect($quote->totalPence)->toBe($expectedTotal);
})->with([
    'Monday: full price' => ['2026-06-01', 1500],
    'Tuesday' => ['2026-06-02', 1200],
    'Wednesday' => ['2026-06-03', 1200],
    'Thursday' => ['2026-06-04', 1200],
    'Friday: full price' => ['2026-06-05', 1500],
    'Sunday: full price' => ['2026-06-07', 1500],
]);

it('does not give the kids midweek discount on other services', function () {
    $main = Service::factory()->create(['price_pence' => 1500]);

    $quote = quoteFor($main, ['startsAt' => london('2026-06-02 10:00')]);

    expect($quote->totalPence)->toBe(1500);
});

it('decides the kids midweek day in London time, not UTC', function () {
    $main = Service::factory()->kids()->create(['price_pence' => 1500]);

    // 00:30 on Tuesday in London is still Monday evening in UTC during British Summer Time.
    $quote = quoteFor($main, ['startsAt' => london('2026-06-02 00:30')->utc()]);

    expect($quote->totalPence)->toBe(1200);
});

it('takes 20% off the whole booking on a second visit', function () {
    $main = Service::factory()->create(['price_pence' => 1800]);
    $extra = Service::factory()->addon()->create(['price_pence' => 500]);

    $quote = quoteFor($main, ['extras' => [$extra], 'secondVisit' => true]);

    expect($quote->totalPence)->toBe(1840)
        ->and($quote->discountOfType(DiscountType::SecondVisit)->amountPence)->toBe(460)
        ->and($quote->discountOfType(DiscountType::SecondVisit)->label)->toBe('Every 2nd visit: 20% off');
});

it('applies the second-visit percentage after the kids midweek discount', function () {
    $main = Service::factory()->kids()->create(['price_pence' => 1500]);

    $quote = quoteFor($main, ['startsAt' => london('2026-06-02 10:00'), 'secondVisit' => true]);

    // £15 - £3 = £12, then 20% off £12 = £9.60
    expect($quote->totalPence)->toBe(960);
});

it('gives no second-visit discount when the owner has switched it off', function () {
    $main = Service::factory()->create(['price_pence' => 1800]);

    $quote = quoteFor($main, [
        'secondVisit' => true,
        'settings' => ShopSetting::factory()->make(['second_visit_percent' => 0]),
    ]);

    expect($quote->totalPence)->toBe(1800);
});

it('takes a percentage promo code off the total, rounded to the nearest penny', function () {
    $main = Service::factory()->create(['price_pence' => 1250]);
    $promoCode = PromoCode::factory()->percent(15)->create();

    $quote = quoteFor($main, ['promoCode' => $promoCode]);

    // 15% of £12.50 is £1.875, which rounds to £1.88
    expect($quote->discountOfType(DiscountType::PromoCode)->amountPence)->toBe(188)
        ->and($quote->totalPence)->toBe(1062);
});

it('takes a fixed promo code off the total', function () {
    $main = Service::factory()->create(['price_pence' => 1800]);
    $promoCode = PromoCode::factory()->fixed(500)->create();

    $quote = quoteFor($main, ['promoCode' => $promoCode]);

    expect($quote->totalPence)->toBe(1300);
});

it('never takes a fixed promo code below zero', function () {
    $main = Service::factory()->create(['price_pence' => 300]);
    $promoCode = PromoCode::factory()->fixed(500)->create();

    $quote = quoteFor($main, ['promoCode' => $promoCode]);

    expect($quote->totalPence)->toBe(0)
        ->and($quote->discountOfType(DiscountType::PromoCode)->amountPence)->toBe(300);
});

it('drops the promo code when the second-visit discount applies', function () {
    $main = Service::factory()->create(['price_pence' => 2000]);
    $promoCode = PromoCode::factory()->percent(15)->create();

    $quote = quoteFor($main, ['promoCode' => $promoCode, 'secondVisit' => true]);

    expect($quote->totalPence)->toBe(1600)
        ->and($quote->discountOfType(DiscountType::PromoCode))->toBeNull()
        ->and($quote->promoCodeNotCombined)->toBeTrue();
});

it('charges the walk-in price for a walk-in, without the midweek discount on top', function () {
    $main = Service::factory()->kids()->create(['price_pence' => 1500, 'walk_in_price_pence' => 1000]);

    $quote = quoteFor($main, ['startsAt' => london('2026-06-02 10:00'), 'source' => BookingSource::WalkIn]);

    expect($quote->totalPence)->toBe(1000);
});

it('charges the booked price online even when a walk-in price exists', function () {
    $main = Service::factory()->create(['price_pence' => 1500, 'walk_in_price_pence' => 1000]);

    $quote = quoteFor($main, ['source' => BookingSource::Online]);

    expect($quote->totalPence)->toBe(1500);
});

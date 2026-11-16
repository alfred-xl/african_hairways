<?php

namespace App\Services;

use App\Data\Quote;
use App\Data\QuoteDiscount;
use App\Data\QuoteLine;
use App\Enums\BookingSource;
use App\Enums\DiscountType;
use App\Enums\PromoCodeType;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Support\Money;
use Carbon\CarbonInterface;

class PricingService
{
    /**
     * Price a main service and its extras from the database's prices.
     *
     * Discounts apply in a fixed order: kids' midweek, then either the second-visit
     * discount or a promo code (never both).
     *
     * @param  iterable<Service>  $extras
     * @param  CarbonInterface|null  $startsAt  The appointment time; day-based discounts need it.
     * @param  PromoCode|null  $promoCode  A code that has already passed its eligibility checks.
     * @param  bool  $secondVisit  Whether this customer's booking earns the every-second-visit discount.
     */
    public function quote(
        Service $main,
        iterable $extras,
        ?CarbonInterface $startsAt,
        ShopSetting $settings,
        ?PromoCode $promoCode = null,
        bool $secondVisit = false,
        BookingSource $source = BookingSource::Online,
    ): Quote {
        $usesWalkInPrice = $source === BookingSource::WalkIn && $main->walk_in_price_pence !== null;

        $lines = [new QuoteLine(
            serviceId: $main->id,
            name: $main->name,
            pricePence: $usesWalkInPrice ? $main->walk_in_price_pence : $main->price_pence,
            durationMinutes: $main->duration_minutes,
            isAddon: false,
        )];

        foreach ($extras as $extra) {
            $lines[] = new QuoteLine($extra->id, $extra->name, $extra->price_pence, $extra->duration_minutes, isAddon: true);
        }

        $subtotal = array_sum(array_map(fn (QuoteLine $line): int => $line->pricePence, $lines));
        $duration = array_sum(array_map(fn (QuoteLine $line): int => $line->durationMinutes, $lines));

        $running = $subtotal;
        $discounts = [];

        // The walk-in price is already the reduced price, so the midweek offer is not taken off it again.
        if ($main->is_kids && ! $usesWalkInPrice && $this->isKidsMidweekDay($startsAt)) {
            $amount = min($settings->kids_midweek_discount_pence, $running);

            if ($amount > 0) {
                $discounts[] = new QuoteDiscount(DiscountType::KidsMidweek, 'Kids midweek '.Money::format($amount).' off', $amount);
                $running -= $amount;
            }
        }

        $secondVisitApplies = $secondVisit && $settings->second_visit_percent > 0;

        if ($secondVisitApplies) {
            $amount = $this->percentOf($running, $settings->second_visit_percent);

            if ($amount > 0) {
                $discounts[] = new QuoteDiscount(DiscountType::SecondVisit, "Every 2nd visit: {$settings->second_visit_percent}% off", $amount);
                $running -= $amount;
            }
        } elseif ($promoCode !== null) {
            $amount = $promoCode->type === PromoCodeType::Percent
                ? $this->percentOf($running, $promoCode->value)
                : min($promoCode->value, $running);

            if ($amount > 0) {
                $discounts[] = new QuoteDiscount(DiscountType::PromoCode, $promoCode->label, $amount);
                $running -= $amount;
            }
        }

        return new Quote(
            lines: $lines,
            discounts: $discounts,
            subtotalPence: $subtotal,
            totalPence: max(0, $running),
            durationMinutes: $duration,
            promoCodeNotCombined: $secondVisitApplies && $promoCode !== null,
        );
    }

    private function isKidsMidweekDay(?CarbonInterface $startsAt): bool
    {
        return $startsAt !== null && in_array(
            $startsAt->copy()->setTimezone(config('shop.timezone'))->dayOfWeek,
            config('shop.kids_midweek_weekdays'),
            strict: true,
        );
    }

    /**
     * A percentage of an amount in pence, rounded to the nearest penny (halves round up).
     */
    private function percentOf(int $pence, int $percent): int
    {
        return min($pence, intdiv($pence * $percent + 50, 100));
    }
}

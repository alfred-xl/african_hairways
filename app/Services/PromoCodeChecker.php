<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Customer;
use App\Models\PromoCode;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PromoCodeChecker
{
    public const NOT_VALID = "That code isn't valid.";

    /**
     * Check the rules that don't depend on who is booking. Returns the reason for refusing, or null if the code passes.
     *
     * @param  CarbonInterface|null  $startsAt  The appointment time, when one has been chosen.
     */
    public function generalRejection(PromoCode $promoCode, ?CarbonInterface $startsAt): ?string
    {
        if (! $promoCode->is_active || ($promoCode->starts_at !== null && $promoCode->starts_at->isFuture())) {
            return self::NOT_VALID;
        }

        if ($promoCode->expires_at !== null && $promoCode->expires_at->isPast()) {
            return "{$promoCode->code} has expired.";
        }

        if ($promoCode->max_redemptions !== null && $promoCode->countedRedemptions()->count() >= $promoCode->max_redemptions) {
            return "{$promoCode->code} has been used up.";
        }

        if ($startsAt !== null && ! $this->appointmentTimeQualifies($promoCode, $startsAt)) {
            return "{$promoCode->code} only works {$this->describeTimeRestriction($promoCode)}. Remove it or pick another time.";
        }

        return null;
    }

    /**
     * Check the rules that depend on the customer's history. These only run when a booking is
     * confirmed, so typing someone's mobile number never reveals whether they have visited.
     */
    public function customerRejection(PromoCode $promoCode, Customer $customer): ?string
    {
        if ($promoCode->first_visit_only && $customer->bookings()->where('status', BookingStatus::Completed)->exists()) {
            return "{$promoCode->code} is for first visits only. Remove it to continue.";
        }

        if ($promoCode->max_redemptions_per_customer !== null) {
            $used = $promoCode->countedRedemptions()->whereBelongsTo($customer)->count();

            if ($used >= $promoCode->max_redemptions_per_customer) {
                return "{$promoCode->code} has already been used with this mobile number. Remove it to continue.";
            }
        }

        return null;
    }

    private function appointmentTimeQualifies(PromoCode $promoCode, CarbonInterface $startsAt): bool
    {
        $local = $startsAt->copy()->setTimezone(config('shop.timezone'));

        if ($promoCode->valid_weekdays !== null && ! in_array($local->dayOfWeek, array_map(intval(...), $promoCode->valid_weekdays), strict: true)) {
            return false;
        }

        return $promoCode->valid_before_time === null || $local->format('H:i:s') < $promoCode->valid_before_time;
    }

    /**
     * Describe a code's day and time limits in plain words, e.g. "Monday to Thursday before 3pm".
     */
    public function describeTimeRestriction(PromoCode $promoCode): string
    {
        $parts = [];

        if ($promoCode->valid_weekdays !== null) {
            $parts[] = $this->describeWeekdays(array_map(intval(...), $promoCode->valid_weekdays));
        }

        if ($promoCode->valid_before_time !== null) {
            $time = Carbon::createFromFormat('H:i:s', $promoCode->valid_before_time);

            $parts[] = 'before '.$time->format($time->minute === 0 ? 'ga' : 'g:ia');
        }

        return implode(' ', $parts);
    }

    /**
     * @param  list<int>  $weekdays  0 = Sunday ... 6 = Saturday
     */
    private function describeWeekdays(array $weekdays): string
    {
        $names = Carbon::getDays();

        // Order Monday-first, the way a UK week is read.
        $ordered = array_values(array_filter([1, 2, 3, 4, 5, 6, 0], fn (int $day): bool => in_array($day, $weekdays, strict: true)));

        if ($ordered === []) {
            return 'on no days';
        }

        $positions = array_map(fn (int $day): int => $day === 0 ? 7 : $day, $ordered);
        $isConsecutive = count($positions) > 2 && end($positions) - $positions[0] === count($positions) - 1;

        if ($isConsecutive) {
            return $names[$ordered[0]].' to '.$names[end($ordered)];
        }

        return collect($ordered)->map(fn (int $day): string => $names[$day].'s')->join(', ', ' and ');
    }
}

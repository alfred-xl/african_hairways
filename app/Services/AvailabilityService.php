<?php

namespace App\Services;

use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\OpeningHour;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Models\StaffWorkingHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AvailabilityService
{
    /**
     * The current time in the shop's timezone.
     */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('shop.timezone'));
    }

    /**
     * Every day in the booking window, flagged with whether the staff member works at all that day.
     * Existing bookings are ignored here: a fully booked day is still a working day.
     *
     * @return list<array{date: CarbonImmutable, working: bool}>
     */
    public function bookableDays(StaffMember $staffMember): array
    {
        $today = $this->now()->startOfDay();
        $windowDays = ShopSetting::current()->booking_window_days;

        $openingHours = OpeningHour::all()->keyBy('day_of_week');
        $workingHours = $staffMember->workingHours()->get()->groupBy('day_of_week');
        $blocks = BlockedTime::query()
            ->affecting($staffMember)
            ->overlapping($today, $today->addDays($windowDays))
            ->get();

        $days = [];

        for ($offset = 0; $offset < $windowDays; $offset++) {
            $date = $today->addDays($offset);
            $windows = $this->windowsFor($date, $openingHours->get($date->dayOfWeek), $workingHours->get($date->dayOfWeek, collect())->all());

            $days[] = [
                'date' => $date,
                'working' => $this->hasTimeOutsideBlocks($windows, $blocks->all()),
            ];
        }

        return $days;
    }

    /**
     * Start times a customer can book for an appointment of the given length.
     *
     * A time is offered only when the whole appointment fits inside the shop's opening hours and the
     * staff member's working pattern, clear of blocked time and other bookings, inside the booking
     * window, and far enough ahead of now.
     *
     * @return list<CarbonImmutable> Start times in the shop's timezone.
     */
    public function slots(StaffMember $staffMember, CarbonInterface $date, int $durationMinutes): array
    {
        $settings = ShopSetting::current();
        $now = $this->now();
        $date = CarbonImmutable::parse($date->format('Y-m-d'), config('shop.timezone'))->startOfDay();
        $today = $now->startOfDay();

        if ($durationMinutes < 1 || $date->lt($today) || $date->gte($today->addDays($settings->booking_window_days))) {
            return [];
        }

        $windows = $this->windowsFor(
            $date,
            OpeningHour::where('day_of_week', $date->dayOfWeek)->first(),
            $staffMember->workingHours()->where('day_of_week', $date->dayOfWeek)->get()->all(),
        );

        if ($windows === []) {
            return [];
        }

        $busy = $this->busyIntervals($staffMember, $date, $date->addDay());
        $earliest = $now->addMinutes($settings->min_lead_minutes);
        $step = max(5, $settings->slot_step_minutes);

        $slots = [];

        foreach ($windows as [$windowStart, $windowEnd]) {
            for ($start = $windowStart; $start->addMinutes($durationMinutes)->lte($windowEnd); $start = $start->addMinutes($step)) {
                if ($start->lt($earliest)) {
                    continue;
                }

                if (! $this->overlapsAny($start, $start->addMinutes($durationMinutes), $busy)) {
                    $slots[] = $start;
                }
            }
        }

        return $slots;
    }

    /**
     * Whether a customer may book this exact start time. Run inside the booking transaction, this is the final say.
     */
    public function isBookable(StaffMember $staffMember, CarbonInterface $startsAt, int $durationMinutes): bool
    {
        $startsAt = CarbonImmutable::instance($startsAt)->setTimezone(config('shop.timezone'));

        foreach ($this->slots($staffMember, $startsAt, $durationMinutes) as $slot) {
            if ($slot->equalTo($startsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an appointment would overlap another booking for the same staff member.
     * Staff adding a booking by hand are held to this rule only.
     */
    public function clashesWithBooking(StaffMember $staffMember, CarbonInterface $startsAt, int $durationMinutes): bool
    {
        return Booking::query()
            ->whereBelongsTo($staffMember)
            ->occupyingTime()
            ->overlapping($startsAt, $startsAt->copy()->addMinutes($durationMinutes))
            ->exists();
    }

    /**
     * The stretches of a day a staff member is at work: the overlap of the shop's
     * opening hours with each row of their working pattern.
     *
     * @param  list<StaffWorkingHour>  $workingHours
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function windowsFor(CarbonImmutable $date, ?OpeningHour $openingHour, array $workingHours): array
    {
        if ($openingHour === null || ! $openingHour->isOpen()) {
            return [];
        }

        $opens = $date->setTimeFromTimeString($openingHour->opens_at);
        $closes = $date->setTimeFromTimeString($openingHour->closes_at);

        $windows = [];

        foreach ($workingHours as $workingHour) {
            $start = $date->setTimeFromTimeString($workingHour->starts_at)->max($opens);
            $end = $date->setTimeFromTimeString($workingHour->ends_at)->min($closes);

            if ($start->lt($end)) {
                $windows[] = [$start, $end];
            }
        }

        usort($windows, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $windows;
    }

    /**
     * Bookings and blocked time that take up a staff member's time between two moments.
     * Only start and end times are loaded: no customer details leave the database here.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function busyIntervals(StaffMember $staffMember, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $bookings = Booking::query()
            ->whereBelongsTo($staffMember)
            ->occupyingTime()
            ->overlapping($from, $to)
            ->get(['starts_at', 'ends_at']);

        $blocks = BlockedTime::query()
            ->affecting($staffMember)
            ->overlapping($from, $to)
            ->get(['starts_at', 'ends_at']);

        return $bookings->concat($blocks)
            ->map(fn (Booking|BlockedTime $busy): array => [$busy->starts_at, $busy->ends_at])
            ->all();
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     */
    private function overlapsAny(CarbonImmutable $start, CarbonImmutable $end, array $intervals): bool
    {
        foreach ($intervals as [$busyStart, $busyEnd]) {
            if ($start->lt($busyEnd) && $busyStart->lt($end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any part of the working windows is left once blocked time is taken out.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows
     * @param  list<BlockedTime>  $blocks
     */
    private function hasTimeOutsideBlocks(array $windows, array $blocks): bool
    {
        usort($blocks, fn (BlockedTime $a, BlockedTime $b): int => $a->starts_at <=> $b->starts_at);

        foreach ($windows as [$cursor, $windowEnd]) {
            foreach ($blocks as $block) {
                if ($block->starts_at->gt($cursor)) {
                    break;
                }

                $cursor = $cursor->max($block->ends_at);
            }

            if ($cursor->lt($windowEnd)) {
                return true;
            }
        }

        return false;
    }
}

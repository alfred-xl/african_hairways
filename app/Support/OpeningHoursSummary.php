<?php

namespace App\Support;

use App\Models\OpeningHour;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OpeningHoursSummary
{
    /**
     * Describe the week in one line, grouping neighbouring days that share the same hours,
     * e.g. "Mon–Sat 9am–7pm · Sun 10am–4pm".
     *
     * @param  Collection<int, OpeningHour>  $openingHours
     */
    public static function line(Collection $openingHours): string
    {
        $byDay = $openingHours->keyBy('day_of_week');
        $names = Carbon::getDays();
        $groups = [];

        // Monday first, the way a UK week is read.
        foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
            $openingHour = $byDay->get($day);
            $hours = $openingHour?->isOpen()
                ? self::time($openingHour->opens_at).'–'.self::time($openingHour->closes_at)
                : 'closed';

            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['hours'] === $hours) {
                $groups[$last]['to'] = $day;
            } else {
                $groups[] = ['from' => $day, 'to' => $day, 'hours' => $hours];
            }
        }

        return collect($groups)
            ->map(function (array $group) use ($names): string {
                $days = mb_substr($names[$group['from']], 0, 3);

                if ($group['to'] !== $group['from']) {
                    $days .= '–'.mb_substr($names[$group['to']], 0, 3);
                }

                return "{$days} {$group['hours']}";
            })
            ->join(' · ');
    }

    /**
     * "09:00:00" becomes "9am"; "17:30:00" becomes "5:30pm".
     */
    private static function time(string $time): string
    {
        $parsed = Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? "{$time}:00" : $time);

        return $parsed->format($parsed->minute === 0 ? 'ga' : 'g:ia');
    }
}

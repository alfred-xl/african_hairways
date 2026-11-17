<?php

namespace App\Filament\Support;

use Carbon\Carbon;

class TimeOptions
{
    /**
     * Times of day for a select, keyed the way the database stores them ("09:15:00" => "09:15").
     *
     * @return array<string, string>
     */
    public static function everyQuarterHour(): array
    {
        $options = [];

        for ($minutes = 0; $minutes < 24 * 60; $minutes += 15) {
            $label = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $options["{$label}:00"] = $label;
        }

        return $options;
    }

    /**
     * Weekdays for a select, Monday first, keyed 0 (Sunday) to 6 (Saturday).
     *
     * @return array<int, string>
     */
    public static function weekdays(): array
    {
        $names = Carbon::getDays();
        $options = [];

        foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
            $options[$day] = $names[$day];
        }

        return $options;
    }
}

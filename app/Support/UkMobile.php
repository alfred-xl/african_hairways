<?php

namespace App\Support;

class UkMobile
{
    /**
     * Normalise a UK mobile number to E.164 (+447XXXXXXXXX), or null when it is not one.
     */
    public static function normalise(?string $input): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', (string) $input);

        $digits = match (true) {
            str_starts_with($digits, '+44') => substr($digits, 3),
            str_starts_with($digits, '0044') => substr($digits, 4),
            str_starts_with($digits, '44') => substr($digits, 2),
            default => $digits,
        };

        // "+44 (0)7..." is a common way to write a UK number.
        $digits = ltrim($digits, '0');

        if (preg_match('/^7\d{9}$/', $digits) !== 1) {
            return null;
        }

        return '+44'.$digits;
    }

    /**
     * Show a normalised number the way people write it in the UK ("07123 456789").
     */
    public static function display(string $normalised): string
    {
        $national = '0'.substr($normalised, 3);

        return substr($national, 0, 5).' '.substr($national, 5);
    }
}

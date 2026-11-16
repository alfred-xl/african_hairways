<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shop timezone and currency
    |--------------------------------------------------------------------------
    |
    | Dates are stored in UTC and shown in the shop's timezone. Opening hours
    | and working patterns are wall-clock times in this timezone.
    |
    */

    'timezone' => 'Europe/London',

    'currency' => 'GBP',

    /*
    |--------------------------------------------------------------------------
    | Parent company
    |--------------------------------------------------------------------------
    */

    'company' => 'Touch2finish Services Ltd',

    'main_site_url' => env('SHOP_MAIN_SITE_URL', 'https://touch2finish.co.uk'),

    'main_site_label' => 'Touch2finish',

    /*
    |--------------------------------------------------------------------------
    | Booking rules
    |--------------------------------------------------------------------------
    |
    | Day-to-day settings (booking window, slot step, discounts) live in the
    | shop_settings table so the owner can change them in the back office.
    |
    */

    // Weekdays the kids' midweek discount applies to (0 = Sunday ... 6 = Saturday).
    'kids_midweek_weekdays' => [2, 3, 4],

    // The most upcoming online bookings one mobile number can hold, to stop one person filling the diary.
    'max_upcoming_online_bookings' => 5,

];

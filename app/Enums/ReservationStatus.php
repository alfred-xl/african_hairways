<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Reserved = 'reserved';
    case Collected = 'collected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'Waiting for collection',
            self::Collected => 'Collected',
            self::Cancelled => 'Cancelled',
        };
    }
}

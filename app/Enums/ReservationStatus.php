<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasLabel
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

    /**
     * The label Filament shows for this case in selects, tables and badges.
     */
    public function getLabel(): string
    {
        return $this->label();
    }
}

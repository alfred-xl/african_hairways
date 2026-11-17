<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasLabel
{
    case Booked = 'booked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::Completed => 'Done',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
        };
    }

    /**
     * Statuses that hold a staff member's time, so nothing else can be booked over them.
     *
     * @return list<self>
     */
    public static function occupyingTime(): array
    {
        return [self::Booked, self::Completed];
    }

    /**
     * The label Filament shows for this case in selects, tables and badges.
     */
    public function getLabel(): string
    {
        return $this->label();
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookingSource: string implements HasLabel
{
    case Online = 'online';
    case WalkIn = 'walk_in';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
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

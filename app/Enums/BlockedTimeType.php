<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BlockedTimeType: string implements HasLabel
{
    case Lunch = 'lunch';
    case Holiday = 'holiday';
    case Sick = 'sick';
    case Closure = 'closure';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Lunch => 'Lunch',
            self::Holiday => 'Holiday',
            self::Sick => 'Sick day',
            self::Closure => 'Shop closure',
            self::Other => 'Other',
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

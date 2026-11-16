<?php

namespace App\Enums;

enum BlockedTimeType: string
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
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PromoCodeType: string implements HasLabel
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage off',
            self::Fixed => 'Fixed amount off',
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

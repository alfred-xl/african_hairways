<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Owner = 'owner';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Staff => 'Staff',
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

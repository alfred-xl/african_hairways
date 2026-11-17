<?php

namespace App\Filament\Support;

use Illuminate\Support\Facades\Gate;

/**
 * Limits a resource or page to the owner role. Staff don't see it in the menu and can't open it by URL.
 */
trait OwnerOnly
{
    public static function canAccess(): bool
    {
        return Gate::allows('manage-shop');
    }
}

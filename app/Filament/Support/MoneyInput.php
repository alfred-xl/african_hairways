<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;

class MoneyInput
{
    /**
     * A pounds-and-pence field for a column that stores integer pence.
     */
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->prefix('£')
            ->minValue(0)
            ->maxValue(100000)
            ->step(0.01)
            ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : number_format(((int) $state) / 100, 2, '.', ''))
            ->dehydrateStateUsing(fn (mixed $state): ?int => filled($state) ? (int) round(((float) $state) * 100) : null);
    }
}

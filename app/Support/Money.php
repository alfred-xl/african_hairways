<?php

namespace App\Support;

class Money
{
    /**
     * Format pence as pounds, dropping the pence when they are zero ("£15", "£12.50").
     */
    public static function format(int $pence): string
    {
        $sign = $pence < 0 ? '−' : '';
        $pence = abs($pence);

        return $sign.'£'.($pence % 100 === 0
            ? intdiv($pence, 100)
            : number_format($pence / 100, 2, '.', ''));
    }
}

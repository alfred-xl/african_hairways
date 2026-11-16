<?php

use App\Support\Money;

it('formats pence as pounds', function (int $pence, string $expected) {
    expect(Money::format($pence))->toBe($expected);
})->with([
    'whole pounds drop the pence' => [1500, '£15'],
    'pence are kept' => [1250, '£12.50'],
    'under a pound' => [5, '£0.05'],
    'zero' => [0, '£0'],
]);

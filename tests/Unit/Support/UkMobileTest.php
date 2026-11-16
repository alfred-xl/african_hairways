<?php

use App\Support\UkMobile;

it('normalises UK mobile numbers to one format', function (string $input) {
    expect(UkMobile::normalise($input))->toBe('+447700900123');
})->with([
    'national' => '07700900123',
    'with spaces' => '07700 900 123',
    'international' => '+447700900123',
    'international with spaces and dashes' => '+44 7700-900-123',
    'international without plus' => '447700900123',
    '00 prefix' => '00447700900123',
    'bracketed zero' => '+44 (0)7700 900123',
]);

it('rejects numbers that are not UK mobiles', function (string $input) {
    expect(UkMobile::normalise($input))->toBeNull();
})->with([
    'empty' => '',
    'too short' => '0770090012',
    'too long' => '077009001234',
    'London landline' => '02079460000',
    'US number' => '+12025550123',
    'letters' => 'call me',
]);

it('displays a normalised number in the national format', function () {
    expect(UkMobile::display('+447700900123'))->toBe('07700 900123');
});

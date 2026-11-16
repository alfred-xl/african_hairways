<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * An immutable datetime that is always stored in UTC.
 *
 * Eloquent's built-in datetime cast writes a date's wall-clock time and drops its timezone, so a
 * London time assigned during British Summer Time would be saved an hour out. This cast converts first.
 *
 * @implements CastsAttributes<CarbonImmutable, DateTimeInterface|string>
 */
class UtcDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, 'UTC');

        return $date->utc()->format($model->getDateFormat());
    }
}

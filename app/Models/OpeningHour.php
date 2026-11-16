<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\OpeningHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['day_of_week', 'opens_at', 'closes_at', 'is_closed'])]
class OpeningHour extends Model
{
    /** @use HasFactory<OpeningHourFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    public function dayName(): string
    {
        return Carbon::getDays()[$this->day_of_week];
    }

    public function isOpen(): bool
    {
        return ! $this->is_closed && $this->opens_at !== null && $this->closes_at !== null;
    }
}

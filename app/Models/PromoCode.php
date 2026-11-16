<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PromoCodeType;
use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'label', 'note', 'description', 'type', 'value', 'first_visit_only', 'valid_weekdays',
    'valid_before_time', 'max_redemptions', 'max_redemptions_per_customer', 'starts_at', 'expires_at',
    'is_active', 'show_on_offers_page',
])]
class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PromoCodeType::class,
            'value' => 'integer',
            'first_visit_only' => 'boolean',
            'valid_weekdays' => 'array',
            'max_redemptions' => 'integer',
            'max_redemptions_per_customer' => 'integer',
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'show_on_offers_page' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PromoCodeRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    /**
     * Redemptions that still count against usage limits: a cancelled booking gives its use back.
     *
     * @return HasMany<PromoCodeRedemption, $this>
     */
    public function countedRedemptions(): HasMany
    {
        return $this->redemptions()->whereHas(
            'booking',
            fn (Builder $query) => $query->where('status', '!=', BookingStatus::Cancelled),
        );
    }

    public static function normaliseCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }
}

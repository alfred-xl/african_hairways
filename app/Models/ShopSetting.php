<?php

namespace App\Models;

use Database\Factories\ShopSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shop_name', 'tagline', 'phone', 'address', 'booking_window_days', 'slot_step_minutes', 'min_lead_minutes',
    'second_visit_percent', 'kids_midweek_discount_pence', 'free_gift_threshold_pence', 'free_gift_product_id',
    'reservation_hold_days',
])]
class ShopSetting extends Model
{
    /** @use HasFactory<ShopSettingFactory> */
    use HasFactory;

    /**
     * Mirrors the column defaults so a shop with no settings row yet still behaves sensibly.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'shop_name' => 'African Hairways',
        'booking_window_days' => 14,
        'slot_step_minutes' => 15,
        'min_lead_minutes' => 15,
        'second_visit_percent' => 20,
        'kids_midweek_discount_pence' => 300,
        'free_gift_threshold_pence' => 2000,
        'reservation_hold_days' => 7,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_window_days' => 'integer',
            'slot_step_minutes' => 'integer',
            'min_lead_minutes' => 'integer',
            'second_visit_percent' => 'integer',
            'kids_midweek_discount_pence' => 'integer',
            'free_gift_threshold_pence' => 'integer',
            'reservation_hold_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function freeGiftProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'free_gift_product_id');
    }

    /**
     * The shop's single settings row.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->first() ?? new static;
    }
}

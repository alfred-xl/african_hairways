<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'reference', 'access_token', 'staff_member_id', 'customer_id', 'customer_name', 'email', 'notes',
    'starts_at', 'ends_at', 'duration_minutes', 'status', 'source', 'subtotal_pence', 'discount_pence',
    'total_pence', 'promo_code_id', 'pay_staff_directly', 'created_by_user_id', 'completed_at',
    'cancelled_at', 'cancelled_by',
])]
#[Hidden(['access_token'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => BookingStatus::Booked->value,
        'source' => BookingSource::Online->value,
        'discount_pence' => 0,
        'pay_staff_directly' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'duration_minutes' => 'integer',
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
            'subtotal_pence' => 'integer',
            'discount_pence' => 'integer',
            'total_pence' => 'integer',
            'pay_staff_directly' => 'boolean',
            'completed_at' => UtcDateTime::class,
            'cancelled_at' => UtcDateTime::class,
        ];
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<PromoCode, $this>
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * @return HasMany<BookingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class)->orderBy('is_addon')->orderBy('id');
    }

    /**
     * @return HasMany<BookingDiscount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(BookingDiscount::class)->orderBy('id');
    }

    /**
     * @return HasOne<PromoCodeRedemption, $this>
     */
    public function promoCodeRedemption(): HasOne
    {
        return $this->hasOne(PromoCodeRedemption::class);
    }

    /**
     * Bookings that hold the staff member's time.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function occupyingTime(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::occupyingTime());
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('starts_at', '<', $to->utc())->where('ends_at', '>', $from->utc());
    }

    public function localStartsAt(): CarbonImmutable
    {
        return $this->starts_at->setTimezone(config('shop.timezone'));
    }

    public function localEndsAt(): CarbonImmutable
    {
        return $this->ends_at->setTimezone(config('shop.timezone'));
    }

    public function serviceSummary(): string
    {
        return $this->items->pluck('name')->join(' + ');
    }

    public function canBeCancelledByCustomer(): bool
    {
        return $this->status === BookingStatus::Booked && $this->starts_at->isFuture();
    }
}

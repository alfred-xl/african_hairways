<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\ReservationStatus;
use Database\Factories\ProductReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'customer_id', 'customer_name', 'status', 'total_pence', 'collect_by', 'collected_at'])]
class ProductReservation extends Model
{
    /** @use HasFactory<ProductReservationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ReservationStatus::Reserved->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'total_pence' => 'integer',
            'collect_by' => 'immutable_date',
            'collected_at' => UtcDateTime::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<ProductReservationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductReservationItem::class)->orderBy('is_free_gift')->orderBy('id');
    }

    public function itemsSummary(): string
    {
        return $this->items
            ->map(fn (ProductReservationItem $item): string => $item->quantity.'× '.$item->name.($item->is_free_gift ? ' (free)' : ''))
            ->join(', ');
    }
}

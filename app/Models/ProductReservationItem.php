<?php

namespace App\Models;

use Database\Factories\ProductReservationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_reservation_id', 'product_id', 'name', 'quantity', 'unit_price_pence', 'is_free_gift'])]
class ProductReservationItem extends Model
{
    /** @use HasFactory<ProductReservationItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_pence' => 'integer',
            'is_free_gift' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProductReservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ProductReservation::class, 'product_reservation_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

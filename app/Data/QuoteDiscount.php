<?php

namespace App\Data;

use App\Enums\DiscountType;

final readonly class QuoteDiscount
{
    public function __construct(
        public DiscountType $type,
        public string $label,
        public int $amountPence,
    ) {}
}

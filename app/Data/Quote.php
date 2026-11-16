<?php

namespace App\Data;

use App\Enums\DiscountType;

final readonly class Quote
{
    /**
     * @param  list<QuoteLine>  $lines
     * @param  list<QuoteDiscount>  $discounts
     * @param  bool  $promoCodeNotCombined  A promo code was given but dropped because the second-visit discount applied.
     */
    public function __construct(
        public array $lines,
        public array $discounts,
        public int $subtotalPence,
        public int $totalPence,
        public int $durationMinutes,
        public bool $promoCodeNotCombined = false,
    ) {}

    public function discountPence(): int
    {
        return $this->subtotalPence - $this->totalPence;
    }

    public function discountOfType(DiscountType $type): ?QuoteDiscount
    {
        foreach ($this->discounts as $discount) {
            if ($discount->type === $type) {
                return $discount;
            }
        }

        return null;
    }
}

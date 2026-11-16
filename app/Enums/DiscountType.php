<?php

namespace App\Enums;

enum DiscountType: string
{
    case KidsMidweek = 'kids_midweek';
    case SecondVisit = 'second_visit';
    case PromoCode = 'promo_code';
}

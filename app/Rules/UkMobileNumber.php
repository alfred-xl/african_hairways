<?php

namespace App\Rules;

use App\Support\UkMobile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UkMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || UkMobile::normalise($value) === null) {
            $fail('Enter a UK mobile number, like 07123 456789.');
        }
    }
}

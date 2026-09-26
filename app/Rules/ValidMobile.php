<?php

namespace App\Rules;

use App\Support\Identifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Identifier::normalizeMobile($value) === null) {
            $fail('Please enter a valid 10 digit mobile number.');
        }
    }
}

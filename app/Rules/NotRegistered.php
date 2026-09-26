<?php

namespace App\Rules;

use App\Models\User;
use App\Support\Identifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Uniqueness of an email/mobile among *verified* accounts only. Unverified
 * registrations are disposable and get replaced (see RegistrationService).
 */
class NotRegistered implements ValidationRule
{
    /** @param  'email'|'mobile'  $column */
    public function __construct(private readonly string $column)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $normalized = $this->column === 'mobile'
            ? Identifier::normalizeMobile($value)
            : mb_strtolower(trim($value));

        if ($normalized !== null && User::whereNotNull('mobile_verified_at')->where($this->column, $normalized)->exists()) {
            $fail($this->column === 'mobile'
                ? 'This mobile number is already registered.'
                : 'This email is already registered.');
        }
    }
}

<?php

namespace App\Support;

/**
 * A login identifier: either an email address or a 10-digit mobile number.
 *
 * Normalising in one place guarantees that what the user types at login, what
 * we store, and what we look up in the forgot-password flow all agree.
 */
final class Identifier
{
    public const EMAIL = 'email';

    public const MOBILE = 'mobile';

    private function __construct(
        public readonly string $type,
        public readonly string $value,
    ) {
    }

    /** Returns null when the input is neither a valid email nor a valid mobile. */
    public static function parse(?string $input): ?self
    {
        $input = trim((string) $input);

        if ($input === '') {
            return null;
        }

        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return new self(self::EMAIL, mb_strtolower($input));
        }

        $mobile = self::normalizeMobile($input);

        return $mobile === null ? null : new self(self::MOBILE, $mobile);
    }

    /**
     * Reduces "+91 98765-43210", "098765 43210" etc. to the bare 10 digits.
     * Indian mobile format (starts 6-9), matching the admin panel design.
     */
    public static function normalizeMobile(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }

    public function column(): string
    {
        return $this->type;
    }

    public function isEmail(): bool
    {
        return $this->type === self::EMAIL;
    }

    /** "s****@example.com" / "98******10" — for logs and user-facing hints. */
    public function masked(): string
    {
        if ($this->isEmail()) {
            [$local, $domain] = explode('@', $this->value, 2);

            return substr($local, 0, 1) . str_repeat('*', max(strlen($local) - 1, 3)) . '@' . $domain;
        }

        return substr($this->value, 0, 2) . str_repeat('*', 6) . substr($this->value, -2);
    }
}

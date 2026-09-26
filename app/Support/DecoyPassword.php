<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Constant-cost password check for logins where the account may not exist.
 *
 * Verifying against a real hash when the account exists but skipping the hash
 * when it does not would make "unknown account" answer measurably faster than
 * "wrong password". Always doing one comparison closes that timing gap.
 */
final class DecoyPassword
{
    private static ?string $hash = null;

    /** @param  string|null  $hash  the account's stored hash, or null if there is none */
    public static function check(string $plain, ?string $hash): bool
    {
        return Hash::check($plain, $hash ?? (self::$hash ??= Hash::make(Str::random(40))));
    }
}

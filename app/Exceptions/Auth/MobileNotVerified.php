<?php

namespace App\Exceptions\Auth;

use App\Models\User;
use RuntimeException;

/**
 * Raised only AFTER the password was verified, so it reveals nothing to a
 * stranger. The app responds by opening the OTP screen.
 */
class MobileNotVerified extends RuntimeException
{
    public function __construct(public readonly User $user)
    {
        parent::__construct('Mobile number is not verified.');
    }
}

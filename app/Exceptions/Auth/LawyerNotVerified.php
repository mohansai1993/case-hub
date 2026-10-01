<?php

namespace App\Exceptions\Auth;

use App\Models\User;
use RuntimeException;

/**
 * Raised only AFTER the password was verified, so it reveals nothing to a
 * stranger. A lawyer cannot sign in until an admin has reviewed and
 * approved their profile (verification_status Verified).
 */
class LawyerNotVerified extends RuntimeException
{
    public function __construct(public readonly User $user)
    {
        parent::__construct('Lawyer account is not verified.');
    }
}

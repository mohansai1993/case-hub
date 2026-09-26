<?php

namespace App\Exceptions\Auth;

use RuntimeException;

/**
 * Raised only AFTER the password was verified, so telling the user their
 * account is deactivated does not help an attacker enumerate accounts.
 */
class AccountInactive extends RuntimeException
{
}

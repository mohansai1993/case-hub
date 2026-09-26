<?php

namespace App\Exceptions\Auth;

use RuntimeException;

/** Unknown account or wrong password. Deliberately indistinguishable. */
class InvalidCredentials extends RuntimeException
{
}

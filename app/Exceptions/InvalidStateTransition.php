<?php

namespace App\Exceptions;

use RuntimeException;

/** An admin action that does not make sense for the account's current state. */
class InvalidStateTransition extends RuntimeException
{
}

<?php

namespace App\Services\AppAuth;

use App\Models\User;

final class RegistrationResult
{
    public function __construct(
        public readonly User $user,
        public readonly bool $otpSent,
    ) {
    }
}

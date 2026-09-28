<?php

namespace App\Services\AppAuth;

use App\Models\User;
use App\Models\UserOtp;
use App\Support\Identifier;
use Illuminate\Support\Facades\DB;

/**
 * Step 2 of registration: proving the user owns the email address.
 */
class AccountVerificationService
{
    public function __construct(private readonly UserOtpService $otps)
    {
    }

    /**
     * (Re)sends the verification code by email. Silent for unknown or already
     * verified addresses; the HTTP layer answers identically either way.
     */
    public function resend(Identifier $email): void
    {
        $user = $this->pendingUser($email);

        if ($user) {
            $this->otps->send($user, UserOtp::PURPOSE_VERIFY_EMAIL, 'email');
        }
    }

    /**
     * Confirms the OTP and activates the account.
     *
     * @return User|null the verified user, or null for a wrong / expired /
     *                   unknown code (callers must not say which)
     */
    public function verify(Identifier $email, string $code): ?User
    {
        $user = $this->pendingUser($email);

        if (! $user || ! $this->otps->verify($user, UserOtp::PURPOSE_VERIFY_EMAIL, $code)) {
            return null;
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->otps->clear($user, UserOtp::PURPOSE_VERIFY_EMAIL);
        });

        return $user->refresh();
    }

    private function pendingUser(Identifier $email): ?User
    {
        $user = User::findByIdentifier($email);

        return $user && ! $user->hasVerifiedEmail() && $user->isActive() ? $user : null;
    }
}

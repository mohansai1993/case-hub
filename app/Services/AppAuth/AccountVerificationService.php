<?php

namespace App\Services\AppAuth;

use App\Models\User;
use App\Models\UserOtp;
use App\Support\Identifier;
use Illuminate\Support\Facades\DB;

/**
 * Step 2 of registration: proving the user owns the mobile number.
 */
class AccountVerificationService
{
    public function __construct(private readonly UserOtpService $otps)
    {
    }

    /**
     * (Re)sends the verification SMS. Silent for unknown or already verified
     * numbers; the HTTP layer answers identically either way.
     */
    public function resend(Identifier $mobile): void
    {
        $user = $this->pendingUser($mobile);

        if ($user) {
            $this->otps->send($user, UserOtp::PURPOSE_VERIFY_MOBILE);
        }
    }

    /**
     * Confirms the OTP and activates the account.
     *
     * @return User|null the verified user, or null for a wrong / expired /
     *                   unknown code (callers must not say which)
     */
    public function verify(Identifier $mobile, string $code): ?User
    {
        $user = $this->pendingUser($mobile);

        if (! $user || ! $this->otps->verify($user, UserOtp::PURPOSE_VERIFY_MOBILE, $code)) {
            return null;
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['mobile_verified_at' => now()])->save();
            $this->otps->clear($user, UserOtp::PURPOSE_VERIFY_MOBILE);
        });

        return $user->refresh();
    }

    private function pendingUser(Identifier $mobile): ?User
    {
        $user = User::findByIdentifier($mobile);

        return $user && ! $user->hasVerifiedMobile() && $user->isActive() ? $user : null;
    }
}

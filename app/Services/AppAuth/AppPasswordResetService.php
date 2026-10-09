<?php

namespace App\Services\AppAuth;

use App\Models\User;
use App\Models\UserOtp;
use App\Support\Identifier;
use Illuminate\Support\Str;

/**
 * "Forgot password?" for clients and lawyers. The OTP always goes out by
 * email (same as the registration verification code) - email is a mandatory
 * field on every account, and no SMS provider is wired up yet, so sending to
 * a mobile number would silently (or, in production, loudly) fail.
 */
class AppPasswordResetService
{
    private const PURPOSE = UserOtp::PURPOSE_RESET_PASSWORD;

    public function __construct(private readonly UserOtpService $otps)
    {
    }

    /** Silent for unknown / unverified / inactive accounts. */
    public function sendOtp(Identifier $identifier): void
    {
        $user = $this->eligibleUser($identifier);

        if ($user) {
            $this->otps->send($user, self::PURPOSE, 'email');
        }
    }

    /** @return string|null a one-time reset token, or null if the code is not valid */
    public function verifyOtp(Identifier $identifier, string $code): ?string
    {
        $user = $this->eligibleUser($identifier);

        if (! $user || ! $this->otps->verify($user, self::PURPOSE, $code)) {
            return null;
        }

        return $this->otps->issueToken($user, self::PURPOSE);
    }

    public function resetPassword(Identifier $identifier, string $token, string $newPassword): bool
    {
        $user = $this->eligibleUser($identifier);

        if (! $user) {
            return false;
        }

        return $this->otps->redeemToken($user, self::PURPOSE, $token, function () use ($user, $newPassword) {
            $user->forceFill([
                'password' => $newPassword,
                'remember_token' => Str::random(60),
            ])->save();

            // Sign the account out of every device.
            $user->tokens()->delete();
        });
    }

    private function eligibleUser(Identifier $identifier): ?User
    {
        $user = User::findByIdentifier($identifier);

        return $user && $user->isActive() && $user->hasVerifiedEmail() ? $user : null;
    }
}

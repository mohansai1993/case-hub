<?php

namespace App\Services\AppAuth;

use App\Models\User;
use App\Models\UserOtp;
use App\Notifications\VerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-time codes for app users (mobile verification, password reset).
 *
 * - codes and tokens are stored only as keyed hashes bound to the user and purpose;
 * - a wrong guess is counted atomically *before* it is compared, so parallel
 *   requests cannot squeeze extra guesses past the limit (essential for a
 *   4-digit code);
 * - a code is single use; requesting a new one replaces the old one;
 * - a code can only be checked while the row is unexpired and under the limit.
 */
class UserOtpService
{
    /**
     * Sends a fresh code by SMS unless one was sent within the resend window.
     *
     * @return bool true if an SMS went out, false if we are still in cooldown
     */
    public function send(User $user, string $purpose): bool
    {
        $existing = UserOtp::where('user_id', $user->getKey())->where('purpose', $purpose)->first();

        if ($existing && $existing->last_sent_at->gt(now()->subSeconds(config('otp.app.resend_after')))) {
            return false;
        }

        $code = $this->generateCode();

        $otp = UserOtp::updateOrCreate(
            ['user_id' => $user->getKey(), 'purpose' => $purpose],
            [
                'code_hash' => $this->hash($user, $purpose, $code),
                'expires_at' => now()->addMinutes(config('otp.app.ttl')),
                'attempts' => 0,
                'last_sent_at' => now(),
                'verified_at' => null,
                'token_hash' => null,
                'token_expires_at' => null,
            ],
        );

        try {
            $user->notify(new VerificationCode($code));
        } catch (Throwable $e) {
            // Nothing was delivered: free the user from the resend cooldown.
            $otp->delete();

            throw $e;
        }

        return true;
    }

    /**
     * Checks a submitted code. On success the row is marked verified (and stays,
     * so a reset token can be issued); it can never be verified twice.
     */
    public function verify(User $user, string $purpose, string $code): bool
    {
        $otp = UserOtp::where('user_id', $user->getKey())->where('purpose', $purpose)->first();

        if (! $otp || $otp->verified_at || $otp->expires_at->isPast()) {
            return false;
        }

        $counted = UserOtp::whereKey($otp->getKey())
            ->whereNull('verified_at')
            ->where('attempts', '<', config('otp.app.max_attempts'))
            ->increment('attempts');

        if (! $counted) {
            return false;
        }

        if (! hash_equals($otp->code_hash, $this->hash($user, $purpose, $code))) {
            return false;
        }

        $otp->forceFill(['verified_at' => now()])->save();

        return true;
    }

    /** Exchanges an already verified code for a one-time token. */
    public function issueToken(User $user, string $purpose): string
    {
        $token = Str::random(64);

        UserOtp::where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->whereNotNull('verified_at')
            ->update([
                'token_hash' => hash('sha256', $token),
                'token_expires_at' => now()->addMinutes(config('otp.app.reset_token_ttl')),
            ]);

        return $token;
    }

    /**
     * Validates a token and, if valid, burns it (and its OTP row) in the same
     * transaction as $callback, so it can only ever be used once.
     */
    public function redeemToken(User $user, string $purpose, string $token, callable $callback): bool
    {
        return DB::transaction(function () use ($user, $purpose, $token, $callback) {
            $otp = UserOtp::where('user_id', $user->getKey())
                ->where('purpose', $purpose)
                ->lockForUpdate()
                ->first();

            if (
                ! $otp
                || ! $otp->verified_at
                || ! $otp->token_hash
                || $otp->token_expires_at?->isPast()
                || ! hash_equals($otp->token_hash, hash('sha256', $token))
            ) {
                return false;
            }

            $callback();
            $otp->delete();

            return true;
        });
    }

    /** Removes any pending code for this purpose (after it has served its use). */
    public function clear(User $user, string $purpose): void
    {
        UserOtp::where('user_id', $user->getKey())->where('purpose', $purpose)->delete();
    }

    private function generateCode(): string
    {
        $length = (int) config('otp.app.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(User $user, string $purpose, string $secret): string
    {
        return hash_hmac('sha256', $user->getKey() . '|' . $purpose . '|' . $secret, (string) config('app.key'));
    }
}

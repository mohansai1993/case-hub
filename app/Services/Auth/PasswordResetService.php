<?php

namespace App\Services\Auth;

use App\Models\Admin;
use App\Models\AdminPasswordReset;
use App\Notifications\PasswordResetOtp;
use App\Support\Identifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Forgot-password by OTP, in three steps:
 *
 *   1. sendOtp()      -> a 6-digit code is delivered by email or SMS
 *   2. verifyOtp()    -> a correct code is exchanged for a one-time reset token
 *   3. resetPassword()-> the token authorises setting a new password
 *
 * Security properties:
 *  - codes and tokens are stored only as keyed hashes;
 *  - wrong guesses are counted atomically; after N the code is dead;
 *  - a code is single-use and a new request replaces the old one;
 *  - callers get the same answer whether or not the account exists;
 *  - a successful reset signs the account out everywhere.
 */
class PasswordResetService
{
    public function __construct(private readonly AuthAuditLogger $audit)
    {
    }

    /**
     * Sends an OTP if the account exists, is active and is not in its resend
     * cooldown. Silent otherwise - the HTTP layer answers identically.
     */
    public function sendOtp(Identifier $identifier, ?string $ip = null): void
    {
        $admin = $this->activeAdmin($identifier);

        if (! $admin) {
            return;
        }

        $existing = AdminPasswordReset::where('admin_id', $admin->getKey())->first();

        if ($existing && $existing->last_sent_at->gt(now()->subSeconds(config('otp.resend_after')))) {
            return;
        }

        $code = $this->generateCode();

        $reset = AdminPasswordReset::updateOrCreate(
            ['admin_id' => $admin->getKey()],
            [
                'otp_hash' => $this->hash($admin, $code),
                'otp_expires_at' => now()->addMinutes(config('otp.ttl')),
                'attempts' => 0,
                'last_sent_at' => now(),
                'verified_at' => null,
                'reset_token_hash' => null,
                'reset_token_expires_at' => null,
                'requested_ip' => $ip,
            ],
        );

        try {
            $admin->notify(new PasswordResetOtp($code, $identifier->type));
        } catch (Throwable $e) {
            // Nothing was delivered: drop the row so the user is not locked
            // out of retrying by the resend cooldown.
            $reset->delete();

            throw $e;
        }

        $this->audit->record(AuthAuditLogger::OTP_SENT, $admin, $identifier);
    }

    /**
     * Exchanges a correct OTP for a reset token, or returns null.
     * A null result never says why (wrong / expired / no such account).
     */
    public function verifyOtp(Identifier $identifier, string $code): ?string
    {
        $admin = $this->activeAdmin($identifier);
        $reset = $admin ? AdminPasswordReset::where('admin_id', $admin->getKey())->first() : null;

        if (! $admin || ! $reset || $reset->verified_at || $reset->otp_expires_at->isPast()) {
            return null;
        }

        // Count the attempt first, atomically and conditionally, so parallel
        // requests cannot squeeze extra guesses past the limit.
        $counted = AdminPasswordReset::whereKey($reset->getKey())
            ->where('attempts', '<', config('otp.max_attempts'))
            ->increment('attempts');

        if (! $counted) {
            return null;
        }

        if (! hash_equals($reset->otp_hash, $this->hash($admin, $code))) {
            $this->audit->record(AuthAuditLogger::OTP_FAILED, $admin, $identifier);

            return null;
        }

        $token = Str::random(64);

        $reset->forceFill([
            'verified_at' => now(),
            'reset_token_hash' => hash('sha256', $token),
            'reset_token_expires_at' => now()->addMinutes(config('otp.reset_token_ttl')),
        ])->save();

        $this->audit->record(AuthAuditLogger::OTP_VERIFIED, $admin, $identifier);

        return $token;
    }

    /** Sets the new password. Returns false when the token is not valid. */
    public function resetPassword(Identifier $identifier, string $token, string $newPassword): bool
    {
        $admin = $this->activeAdmin($identifier);

        if (! $admin) {
            return false;
        }

        $done = DB::transaction(function () use ($admin, $token, $newPassword) {
            $reset = AdminPasswordReset::where('admin_id', $admin->getKey())->lockForUpdate()->first();

            if (
                ! $reset
                || ! $reset->verified_at
                || ! $reset->reset_token_hash
                || $reset->reset_token_expires_at?->isPast()
                || ! hash_equals($reset->reset_token_hash, hash('sha256', $token))
            ) {
                return false;
            }

            $admin->forceFill([
                'password' => $newPassword,
                'remember_token' => Str::random(60), // kills "remember me" cookies
            ])->save();

            $reset->delete(); // single use

            $this->revokeSessions($admin);

            return true;
        });

        $this->audit->record(
            $done ? AuthAuditLogger::PASSWORD_RESET : AuthAuditLogger::PASSWORD_RESET_FAILED,
            $admin,
            $identifier,
        );

        return $done;
    }

    private function activeAdmin(Identifier $identifier): ?Admin
    {
        $admin = Admin::findByIdentifier($identifier);

        return $admin?->isActive() ? $admin : null;
    }

    private function generateCode(): string
    {
        $length = (int) config('otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /** Keyed hash bound to the account, so a stolen hash cannot be replayed. */
    private function hash(Admin $admin, string $secret): string
    {
        return hash_hmac('sha256', $admin->getKey() . '|' . $secret, (string) config('app.key'));
    }

    private function revokeSessions(Admin $admin): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $admin->getKey())
            ->delete();
    }
}

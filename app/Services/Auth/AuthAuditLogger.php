<?php

namespace App\Services\Auth;

use App\Models\Admin;
use App\Models\AdminAuthLog;
use App\Support\Identifier;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Append-only trail of authentication events (who, what, from where).
 * Never stores passwords or OTPs. A failure to write the audit row is
 * reported but never blocks the user-facing flow.
 */
class AuthAuditLogger
{
    public const LOGIN_SUCCESS = 'login.success';
    public const LOGIN_FAILED = 'login.failed';
    public const LOGIN_BLOCKED = 'login.blocked';
    public const LOGIN_LOCKOUT = 'login.lockout';
    public const LOGOUT = 'logout';
    public const OTP_SENT = 'password.otp_sent';
    public const OTP_FAILED = 'password.otp_failed';
    public const OTP_VERIFIED = 'password.otp_verified';
    public const PASSWORD_RESET = 'password.reset';
    public const PASSWORD_RESET_FAILED = 'password.reset_failed';

    public function record(string $event, ?Admin $admin = null, ?Identifier $identifier = null): void
    {
        try {
            $request = request();

            AdminAuthLog::create([
                'admin_id' => $admin?->getKey(),
                'event' => $event,
                'identifier' => $identifier?->value,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to write auth audit log', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }
}

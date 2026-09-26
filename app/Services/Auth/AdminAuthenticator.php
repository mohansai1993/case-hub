<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\AccountInactive;
use App\Exceptions\Auth\InvalidCredentials;
use App\Models\Admin;
use App\Support\Identifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuthenticator
{
    /** "Remember me" lasts 30 days (framework default is ~400). */
    private const REMEMBER_MINUTES = 60 * 24 * 30;

    private static ?string $decoyHash = null;

    public function __construct(private readonly AuthAuditLogger $audit)
    {
    }

    /**
     * Verifies the credentials and signs the admin in.
     *
     * Rate limiting and session regeneration are the caller's job (they need
     * the HTTP request); this class owns the credential rules.
     *
     * @throws InvalidCredentials
     * @throws AccountInactive
     */
    public function attempt(?Identifier $identifier, string $password, bool $remember): Admin
    {
        $admin = $identifier ? Admin::findByIdentifier($identifier) : null;

        // Always run one hash comparison, even for an unknown account, so
        // response time does not reveal whether the account exists.
        $passwordMatches = Hash::check($password, $admin?->password ?? $this->decoyHash());

        if (! $admin || ! $passwordMatches) {
            $this->audit->record(AuthAuditLogger::LOGIN_FAILED, $admin, $identifier);

            throw new InvalidCredentials;
        }

        if (! $admin->isActive()) {
            $this->audit->record(AuthAuditLogger::LOGIN_BLOCKED, $admin, $identifier);

            throw new AccountInactive;
        }

        // Upgrade the stored hash if the configured cost/algorithm changed.
        if (Hash::needsRehash($admin->password)) {
            $admin->password = $password;
        }

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()?->ip(),
        ])->saveQuietly();

        $guard = Auth::guard('admin');
        $guard->setRememberDuration(self::REMEMBER_MINUTES);
        $guard->login($admin, $remember);

        $this->audit->record(AuthAuditLogger::LOGIN_SUCCESS, $admin, $identifier);

        return $admin;
    }

    public function logout(): void
    {
        $admin = Auth::guard('admin')->user();

        Auth::guard('admin')->logout();

        if ($admin) {
            $this->audit->record(AuthAuditLogger::LOGOUT, $admin);
        }
    }

    private function decoyHash(): string
    {
        return self::$decoyHash ??= Hash::make(Str::random(40));
    }
}

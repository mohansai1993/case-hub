<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Services\Sms\LogSmsGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap this binding for a real provider (MSG91, Twilio, ...) when chosen.
        $this->app->bind(SmsGateway::class, match (config('otp.sms_driver')) {
            'log' => LogSmsGateway::class,
            default => LogSmsGateway::class,
        });
    }

    public function boot(): void
    {
        $this->configurePasswordRules();
        $this->configureRateLimiters();
    }

    /**
     * One place that defines "a strong enough password" for the whole app.
     */
    private function configurePasswordRules(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8)->letters()->mixedCase()->numbers();

            // Also reject passwords found in known breaches (calls an external
            // API, so only where it is reachable and matters).
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Coarse per-IP limits. Login additionally has a per-account limiter inside
     * LoginRequest, which is what stops password guessing against one admin.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('otp-send', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(10)->by(sha1(strtolower((string) $request->input('identifier')) . '|' . $request->ip())),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perHour(30)->by(sha1(strtolower((string) $request->input('identifier')) . '|' . $request->ip())),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // ---- Mobile API ----------------------------------------------------

        // Authenticated traffic is limited per user, anonymous per IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user('sanctum')?->getAuthIdentifier() ?: $request->ip()));

        // Sign-ups: a person registers once; a script registers thousands.
        RateLimiter::for('api-register', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(20)->by($request->ip()),
        ]);

        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // The OTP endpoints are the ones that cost money (SMS) and guard 4-digit
        // codes, so they are limited both per IP and per target number.
        $target = fn (Request $request) => sha1(strtolower((string) ($request->input('mobile') ?? $request->input('identifier'))) . '|' . $request->ip());

        RateLimiter::for('api-otp-send', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(10)->by($target($request)),
        ]);

        RateLimiter::for('api-otp-verify', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perHour(20)->by($target($request)),
        ]);

        RateLimiter::for('api-password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }

}

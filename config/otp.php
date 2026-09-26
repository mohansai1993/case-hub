<?php

return [

    /*
    |--------------------------------------------------------------------------
    | One-time password (forgot password) settings
    |--------------------------------------------------------------------------
    */

    'length' => 6,

    // Minutes an OTP stays valid.
    'ttl' => (int) env('OTP_TTL_MINUTES', 10),

    // Seconds before another OTP may be requested for the same account.
    'resend_after' => (int) env('OTP_RESEND_SECONDS', 60),

    // Wrong guesses allowed before the OTP is burned and a new one is needed.
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    // Minutes the reset token (issued after a correct OTP) stays valid.
    'reset_token_ttl' => (int) env('OTP_RESET_TOKEN_TTL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Mobile app (client / lawyer) OTP
    |--------------------------------------------------------------------------
    |
    | The app screens use a 4-digit code sent by SMS. A short code has only
    | 10,000 combinations, so the attempt limit is what protects it: keep
    | max_attempts low.
    |
    */

    'app' => [
        'length' => (int) env('APP_OTP_LENGTH', 4),
        'ttl' => (int) env('APP_OTP_TTL_MINUTES', 10),
        'resend_after' => (int) env('APP_OTP_RESEND_SECONDS', 59),
        'max_attempts' => (int) env('APP_OTP_MAX_ATTEMPTS', 5),
        'reset_token_ttl' => (int) env('APP_OTP_RESET_TOKEN_TTL_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS delivery
    |--------------------------------------------------------------------------
    |
    | Supported: "log" (development only — never delivers).
    | No SMS provider has been chosen yet. To go live, implement
    | App\Contracts\SmsGateway for your provider and bind it in
    | AppServiceProvider (or add a driver here).
    |
    */

    'sms_driver' => env('SMS_DRIVER', 'log'),

];

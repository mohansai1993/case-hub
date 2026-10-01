<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription billing
    |--------------------------------------------------------------------------
    |
    | Supported: "log" (development only - never charges a real card).
    | No payment gateway has been chosen yet (Paystack, Flutterwave, ...). To
    | go live, implement App\Contracts\BillingGateway for your provider and
    | bind it in AppServiceProvider (or add a driver here).
    |
    */

    'driver' => env('BILLING_DRIVER', 'log'),

    // DANGER: lets the "log" driver (which never charges a real card) run in
    // production instead of refusing. Only for using the app before a real
    // gateway is wired - every "successful" charge is fake. Turn this back
    // off the moment a real BillingGateway is bound. See docs/06 section 5.
    'allow_log_driver_in_production' => (bool) env('BILLING_ALLOW_LOG_IN_PRODUCTION', false),

    // Currency shown to clients and used in admin plan pricing.
    'currency' => env('BILLING_CURRENCY', 'NGN'),
    'currency_symbol' => env('BILLING_CURRENCY_SYMBOL', '₦'),

    // Days a cancelled subscription keeps read access before being restricted.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

];

<?php

use App\Models\UserOtp;
use Illuminate\Support\Facades\Schedule;

// Housekeeping (needs `php artisan schedule:run` every minute via cron / Task Scheduler).

// Expired mobile API tokens.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// One-time codes that expired more than a day ago.
Schedule::call(fn () => UserOtp::where('expires_at', '<', now()->subDay())->delete())
    ->name('prune-user-otps')
    ->daily();

// Cancelled subscriptions whose 7-day grace period has run out -> Restricted.
Schedule::command('subscriptions:expire-grace-periods')->daily();

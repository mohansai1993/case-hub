<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptionGracePeriods extends Command
{
    protected $signature = 'subscriptions:expire-grace-periods';

    protected $description = 'Restricts clients whose 7-day cancellation grace period has ended.';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expireOverdueGracePeriods();

        $this->info("{$count} subscription(s) restricted.");

        return self::SUCCESS;
    }
}

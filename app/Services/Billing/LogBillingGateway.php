<?php

namespace App\Services\Billing;

use App\Contracts\BillingGateway;
use App\Models\ClientSubscription;
use App\Models\User;
use App\Support\Billing\ChargeResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Development stand-in: every charge "succeeds" and is written to the log
 * instead of calling a real payment gateway.
 *
 * Refuses to run in production so an unconfigured gateway fails loudly
 * rather than silently taking no one's money while pretending it did.
 */
class LogBillingGateway implements BillingGateway
{
    public function charge(User $client, int $amountNaira, string $reason): ChargeResult
    {
        $this->guardProduction();

        Log::info("[billing:log] charged NGN{$amountNaira} to client {$client->user_id} ({$reason})");

        return new ChargeResult(successful: true, reference: 'log-' . Str::random(16));
    }

    public function cancelRecurring(ClientSubscription $subscription): void
    {
        $this->guardProduction();

        Log::info("[billing:log] cancelled recurring billing for client {$subscription->client_id}");
    }

    private function guardProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('No payment gateway is configured. Bind an App\Contracts\BillingGateway implementation.');
        }
    }
}

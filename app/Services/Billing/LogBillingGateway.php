<?php

namespace App\Services\Billing;

use App\Contracts\BillingGateway;
use App\Models\ClientSubscription;
use App\Models\User;
use App\Support\Billing\ChargeResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Stand-in: every charge "succeeds" and is written to the log instead of
 * calling a real payment gateway. No real money ever moves through this
 * class, in any environment - see docs/06-billing-and-storage.md section 5
 * for wiring a real gateway (Paystack/Flutterwave). Logs a warning (not
 * info) when it runs in production so "this charge was fake" stays visible.
 */
class LogBillingGateway implements BillingGateway
{
    public function charge(User $client, int $amountNaira, string $reason): ChargeResult
    {
        $this->logFakeCharge("charged NGN{$amountNaira} to client {$client->user_id} ({$reason})");

        return new ChargeResult(successful: true, reference: 'log-' . Str::random(16));
    }

    public function cancelRecurring(ClientSubscription $subscription): void
    {
        $this->logFakeCharge("cancelled recurring billing for client {$subscription->client_id}");
    }

    private function logFakeCharge(string $message): void
    {
        if (app()->isProduction()) {
            Log::warning("[billing:log] {$message} - NO REAL GATEWAY CONFIGURED, no money moved.");

            return;
        }

        Log::info("[billing:log] {$message}");
    }
}

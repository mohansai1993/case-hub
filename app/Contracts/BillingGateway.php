<?php

namespace App\Contracts;

use App\Models\ClientSubscription;
use App\Models\User;
use App\Support\Billing\ChargeResult;

/**
 * Whichever payment gateway ends up chosen (Paystack, Flutterwave, ...) is
 * wired behind this contract - nothing else in the billing code talks to a
 * gateway SDK directly.
 */
interface BillingGateway
{
    /**
     * Charges the client's saved payment method for this amount right now:
     * the first charge on subscribe/upgrade/downgrade, or a renewal retry.
     * Keyed on the client (not a subscription row) because the very first
     * charge, on subscribe, happens before any subscription row exists.
     *
     * A declined card is a normal ChargeResult(successful: false, ...), not
     * an exception - only an unexpected/transport failure should throw.
     *
     * @param  string  $reason  "subscribe" | "upgrade" | "downgrade" | "renewal"
     */
    public function charge(User $client, int $amountNaira, string $reason): ChargeResult;

    /** Stops the gateway's own auto-renewal so no further charges happen. */
    public function cancelRecurring(ClientSubscription $subscription): void;
}

<?php

namespace App\Services\Billing;

use App\Contracts\BillingGateway;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidStateTransition;
use App\Models\CaseDocument;
use App\Models\ClientSubscription;
use App\Models\Plan;
use App\Models\SubscriptionCharge;
use App\Models\User;
use App\Notifications\SubscriptionCancelled;
use App\Notifications\SubscriptionRestricted;
use Illuminate\Support\Facades\DB;

/**
 * The subscription state machine: subscribe / upgrade / downgrade / cancel.
 *
 * Billing rule throughout: the gateway is always charged BEFORE any local
 * state change, and never inside a DB transaction - an external charge that
 * succeeded must never be undone by an unrelated DB rollback, and a DB
 * failure must never leave a subscription row changed without a matching
 * successful charge behind it.
 */
class SubscriptionService
{
    public function __construct(
        private readonly BillingGateway $gateway,
        private readonly StorageQuotaService $quota,
    ) {
    }

    /** @throws InvalidStateTransition account already has a plan, or the card was declined */
    public function subscribe(User $client, Plan $plan): ClientSubscription
    {
        if ($client->subscription) {
            throw new InvalidStateTransition('You already have a subscription. Use upgrade or downgrade instead.');
        }

        $charge = $this->chargeAndRecord($client, null, $plan, 'subscribe');

        $subscription = DB::transaction(fn () => ClientSubscription::create([
            'client_id' => $client->user_id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => now()->addMonth(),
        ]));

        $charge->update(['client_subscription_id' => $subscription->id]);

        // $client->subscription was likely already resolved (to null) by the
        // "already have one?" check above; without this, anything that reuses
        // this same $client instance afterward would see a stale null.
        $client->setRelation('subscription', $subscription);

        return $subscription;
    }

    /** @throws InvalidStateTransition no subscription yet, plan is not actually bigger, or the card was declined */
    public function upgrade(User $client, Plan $newPlan): ClientSubscription
    {
        $subscription = $this->requireSubscription($client);

        if ($newPlan->storageLimitBytes() <= $subscription->plan->storageLimitBytes()) {
            throw new InvalidStateTransition('The selected plan is not larger than your current plan.');
        }

        return $this->replacePlan($client, $subscription, $newPlan, 'upgrade');
    }

    /** @throws InvalidStateTransition no subscription yet, plan is not actually smaller, or the card was declined */
    public function downgrade(User $client, Plan $newPlan): ClientSubscription
    {
        $subscription = $this->requireSubscription($client);

        if ($newPlan->storageLimitBytes() >= $subscription->plan->storageLimitBytes()) {
            throw new InvalidStateTransition('The selected plan is not smaller than your current plan.');
        }

        $subscription = $this->replacePlan($client, $subscription, $newPlan, 'downgrade');

        // Oldest files first, until usage fits the new, smaller limit.
        $this->hideOldestFilesOverLimit($client, $newPlan->storageLimitBytes());

        return $subscription;
    }

    /** @throws InvalidStateTransition no subscription, or already cancelled/restricted */
    public function cancel(User $client): ClientSubscription
    {
        $subscription = $this->requireSubscription($client);

        if (! $subscription->isActive()) {
            throw new InvalidStateTransition('This subscription is already cancelled.');
        }

        $this->gateway->cancelRecurring($subscription);

        $graceEndsAt = now()->addDays((int) config('billing.grace_days'));

        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'grace_ends_at' => $graceEndsAt,
        ]);

        $client->notify(new SubscriptionCancelled($graceEndsAt));

        return $subscription->fresh();
    }

    /** Daily scheduled sweep - flips expired grace periods to Restricted. Returns how many. */
    public function expireOverdueGracePeriods(): int
    {
        $subscriptions = ClientSubscription::where('status', SubscriptionStatus::Cancelled)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->with('client')
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => SubscriptionStatus::Restricted, 'restricted_at' => now()]);
            $subscription->client?->notify(new SubscriptionRestricted);
        }

        return $subscriptions->count();
    }

    private function replacePlan(User $client, ClientSubscription $subscription, Plan $newPlan, string $reason): ClientSubscription
    {
        $this->chargeAndRecord($client, $subscription, $newPlan, $reason);

        return DB::transaction(function () use ($subscription, $newPlan) {
            $subscription->update([
                'plan_id' => $newPlan->id,
                'status' => SubscriptionStatus::Active,
                'current_period_ends_at' => now()->addMonth(),
                'cancelled_at' => null,
                'grace_ends_at' => null,
                'restricted_at' => null,
            ]);

            return $subscription->fresh();
        });
    }

    /** Charges, always records the attempt (even a failure), and throws if it was declined. */
    private function chargeAndRecord(User $client, ?ClientSubscription $subscription, Plan $plan, string $reason): SubscriptionCharge
    {
        $result = $this->gateway->charge($client, $plan->price, $reason);

        $charge = SubscriptionCharge::create([
            'client_subscription_id' => $subscription?->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'status' => $result->successful ? 'succeeded' : 'failed',
            'reason' => $reason,
            'gateway_reference' => $result->reference,
            'failure_message' => $result->failureMessage,
        ]);

        if (! $result->successful) {
            throw new InvalidStateTransition($result->failureMessage ?? 'Payment was declined. Please try again.');
        }

        return $charge;
    }

    private function hideOldestFilesOverLimit(User $client, int $limitBytes): void
    {
        $excess = $this->quota->usedBytes($client) - $limitBytes;

        if ($excess <= 0) {
            return;
        }

        $documents = CaseDocument::accessible()
            ->whereHas('case', fn ($query) => $query->where('client_id', $client->user_id))
            ->oldest('created_at')
            ->get();

        foreach ($documents as $document) {
            if ($excess <= 0) {
                break;
            }

            // inaccessible_at is system-set, never user mass-assignable.
            $document->forceFill(['inaccessible_at' => now()])->save();
            $excess -= $document->size_bytes;
        }
    }

    private function requireSubscription(User $client): ClientSubscription
    {
        $subscription = $client->subscription;

        if (! $subscription) {
            throw new InvalidStateTransition('You do not have an active subscription yet.');
        }

        return $subscription;
    }
}

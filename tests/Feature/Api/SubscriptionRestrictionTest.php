<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;

class SubscriptionRestrictionTest extends ApiTestCase
{
    public function test_a_restricted_client_is_locked_out_of_cases_and_notifications(): void
    {
        $client = User::factory()->create();
        $service = app(SubscriptionService::class);
        $service->subscribe($client, Plan::factory()->create());
        $service->cancel($client);
        $client->subscription->update(['grace_ends_at' => now()->subDay()]);
        $service->expireOverdueGracePeriods();

        $headers = $this->bearer($client);

        $this->getJson('/api/v1/cases', $headers)->assertForbidden()->assertJsonPath('code', 'subscription_restricted');
        $this->getJson('/api/v1/notifications', $headers)->assertForbidden();
        $this->postJson('/api/v1/device-tokens', ['token' => 'x', 'platform' => 'android'], $headers)->assertForbidden();
    }

    public function test_a_restricted_client_can_still_reach_billing_and_profile(): void
    {
        $client = User::factory()->create();
        $service = app(SubscriptionService::class);
        $service->subscribe($client, Plan::factory()->create());
        $service->cancel($client);
        $client->subscription->update(['grace_ends_at' => now()->subDay()]);
        $service->expireOverdueGracePeriods();

        $headers = $this->bearer($client);

        $this->getJson('/api/v1/subscription', $headers)->assertOk();
        $this->getJson('/api/v1/plans', $headers)->assertOk();
        $this->getJson('/api/v1/auth/me', $headers)->assertOk();
    }

    public function test_a_cancelled_client_still_in_grace_is_not_restricted_yet(): void
    {
        $client = User::factory()->create();
        $service = app(SubscriptionService::class);
        $service->subscribe($client, Plan::factory()->create());
        $service->cancel($client);

        $this->getJson('/api/v1/cases', $this->bearer($client))->assertOk();
    }

    public function test_lawyers_are_never_affected_by_this_gate(): void
    {
        $lawyer = User::factory()->lawyer()->create();

        $this->getJson('/api/v1/cases', $this->bearer($lawyer))->assertOk();
    }
}

<?php

namespace Tests\Feature\Api;

use App\Contracts\BillingGateway;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Support\Billing\ChargeResult;

class SubscriptionTest extends ApiTestCase
{
    private const SHOW = '/api/v1/subscription';
    private const SUBSCRIBE = '/api/v1/subscription/subscribe';
    private const UPGRADE = '/api/v1/subscription/upgrade';
    private const DOWNGRADE = '/api/v1/subscription/downgrade';
    private const CANCEL = '/api/v1/subscription/cancel';

    private function givePlan(User $client, ?Plan $plan = null): Plan
    {
        $plan ??= Plan::factory()->create();
        app(SubscriptionService::class)->subscribe($client, $plan);

        return $plan;
    }

    // ---- Access ---------------------------------------------------------------

    public function test_guests_cannot_reach_subscription_endpoints(): void
    {
        $this->getJson(self::SHOW)->assertStatus(401);
    }

    public function test_lawyers_cannot_use_any_subscription_endpoint(): void
    {
        $lawyer = User::factory()->lawyer()->create();
        $plan = Plan::factory()->create();

        $this->getJson(self::SHOW, $this->bearer($lawyer))->assertForbidden();
        $this->postJson(self::SUBSCRIBE, ['plan_id' => $plan->id], $this->bearer($lawyer))->assertForbidden();
        $this->postJson(self::CANCEL, [], $this->bearer($lawyer))->assertForbidden();
    }

    // ---- Show -----------------------------------------------------------------

    public function test_show_returns_null_when_the_client_has_no_plan(): void
    {
        $client = User::factory()->create();

        $this->getJson(self::SHOW, $this->bearer($client))->assertOk()->assertJsonPath('data', null);
    }

    public function test_show_reports_status_and_storage_usage(): void
    {
        $client = User::factory()->create();
        $plan = $this->givePlan($client, Plan::factory()->create(['storage_amount' => 1, 'storage_unit' => 'GB', 'price' => 2000]));

        $this->getJson(self::SHOW, $this->bearer($client))
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.id', $plan->id)
            ->assertJsonPath('data.storage.used_bytes', 0)
            ->assertJsonPath('data.storage.is_full', false);
    }

    // ---- Subscribe --------------------------------------------------------------

    public function test_a_client_can_subscribe(): void
    {
        $client = User::factory()->create();
        $plan = Plan::factory()->create();

        $this->postJson(self::SUBSCRIBE, ['plan_id' => $plan->id], $this->bearer($client))
            ->assertCreated()
            ->assertJsonPath('data.plan.id', $plan->id)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_an_inactive_plan_cannot_be_subscribed_to(): void
    {
        $client = User::factory()->create();
        $plan = Plan::factory()->create(['is_active' => false]);

        $this->postJson(self::SUBSCRIBE, ['plan_id' => $plan->id], $this->bearer($client))
            ->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    }

    public function test_subscribing_twice_fails(): void
    {
        $client = User::factory()->create();
        $this->givePlan($client);

        $this->postJson(self::SUBSCRIBE, ['plan_id' => Plan::factory()->create()->id], $this->bearer($client))
            ->assertUnprocessable();
    }

    public function test_a_declined_card_is_reported_as_422(): void
    {
        $mock = \Mockery::mock(BillingGateway::class);
        $mock->shouldReceive('charge')->andReturn(new ChargeResult(successful: false, failureMessage: 'Card declined.'));
        $this->app->instance(BillingGateway::class, $mock);

        $client = User::factory()->create();
        $plan = Plan::factory()->create();

        $this->postJson(self::SUBSCRIBE, ['plan_id' => $plan->id], $this->bearer($client))
            ->assertUnprocessable()->assertJsonPath('message', 'Card declined.');
    }

    // ---- Upgrade / downgrade -----------------------------------------------------

    public function test_a_client_can_upgrade(): void
    {
        $client = User::factory()->create();
        $this->givePlan($client, Plan::factory()->create(['storage_amount' => 500, 'storage_unit' => 'MB']));
        $bigger = Plan::factory()->create(['storage_amount' => 5, 'storage_unit' => 'GB']);

        $this->postJson(self::UPGRADE, ['plan_id' => $bigger->id], $this->bearer($client))
            ->assertOk()->assertJsonPath('data.plan.id', $bigger->id);
    }

    public function test_downgrade_requires_explicit_confirmation(): void
    {
        $client = User::factory()->create();
        $this->givePlan($client, Plan::factory()->create(['storage_amount' => 5, 'storage_unit' => 'GB']));
        $smaller = Plan::factory()->create(['storage_amount' => 500, 'storage_unit' => 'MB']);

        $this->postJson(self::DOWNGRADE, ['plan_id' => $smaller->id], $this->bearer($client))
            ->assertUnprocessable()->assertJsonValidationErrors('confirmed');

        $this->postJson(self::DOWNGRADE, ['plan_id' => $smaller->id, 'confirmed' => true], $this->bearer($client))
            ->assertOk()->assertJsonPath('data.plan.id', $smaller->id);
    }

    // ---- Cancel -----------------------------------------------------------------

    public function test_a_client_can_cancel_and_sees_the_grace_period(): void
    {
        $client = User::factory()->create();
        $this->givePlan($client);

        $this->postJson(self::CANCEL, [], $this->bearer($client))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonStructure(['data' => ['grace_ends_at']]);
    }

    public function test_cancelling_without_a_subscription_fails(): void
    {
        $client = User::factory()->create();

        $this->postJson(self::CANCEL, [], $this->bearer($client))->assertUnprocessable();
    }
}

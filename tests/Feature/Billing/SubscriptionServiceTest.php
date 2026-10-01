<?php

namespace Tests\Feature\Billing;

use App\Contracts\BillingGateway;
use App\Enums\CaseStatus;
use App\Exceptions\InvalidStateTransition;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\SubscriptionRestricted;
use App\Services\Billing\StorageQuotaService;
use App\Services\Billing\SubscriptionService;
use App\Support\Billing\ChargeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function failingGateway(string $reason = 'Card declined.'): void
    {
        $mock = \Mockery::mock(BillingGateway::class);
        $mock->shouldReceive('charge')->andReturn(new ChargeResult(successful: false, failureMessage: $reason));
        $this->app->instance(BillingGateway::class, $mock);
    }

    // ---- Subscribe --------------------------------------------------------------

    public function test_a_client_can_subscribe_to_a_plan(): void
    {
        $client = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 1500]);

        $subscription = $this->service()->subscribe($client, $plan);

        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status->value);
        $this->assertDatabaseHas('subscription_charges', [
            'client_subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount' => 1500,
            'status' => 'succeeded',
            'reason' => 'subscribe',
        ]);
    }

    public function test_a_client_cannot_subscribe_twice(): void
    {
        $client = User::factory()->create();
        $plan = Plan::factory()->create();
        $this->service()->subscribe($client, $plan);

        $this->expectException(InvalidStateTransition::class);
        $this->service()->subscribe($client, Plan::factory()->create());
    }

    public function test_a_declined_card_blocks_subscribe_and_is_still_logged(): void
    {
        $this->failingGateway('Insufficient funds.');
        $client = User::factory()->create();
        $plan = Plan::factory()->create();

        $this->expectException(InvalidStateTransition::class);

        try {
            $this->service()->subscribe($client, $plan);
        } finally {
            $this->assertDatabaseHas('subscription_charges', ['status' => 'failed', 'failure_message' => 'Insufficient funds.']);
            $this->assertNull($client->subscription()->first());
        }
    }

    // ---- Upgrade / downgrade direction guards --------------------------------------

    public function test_upgrade_rejects_a_plan_that_is_not_bigger(): void
    {
        $client = User::factory()->create();
        $plan = Plan::factory()->create(['storage_amount' => 5, 'storage_unit' => 'GB']);
        $this->service()->subscribe($client, $plan);

        $this->expectException(InvalidStateTransition::class);
        $this->service()->upgrade($client, Plan::factory()->create(['storage_amount' => 5, 'storage_unit' => 'GB']));
    }

    public function test_downgrade_rejects_a_plan_that_is_not_smaller(): void
    {
        $client = User::factory()->create();
        $plan = Plan::factory()->create(['storage_amount' => 1, 'storage_unit' => 'GB']);
        $this->service()->subscribe($client, $plan);

        $this->expectException(InvalidStateTransition::class);
        $this->service()->downgrade($client, Plan::factory()->create(['storage_amount' => 2, 'storage_unit' => 'GB']));
    }

    public function test_a_declined_card_blocks_an_upgrade_and_keeps_the_old_plan(): void
    {
        $client = User::factory()->create();
        $old = Plan::factory()->create(['storage_amount' => 500, 'storage_unit' => 'MB']);
        $this->service()->subscribe($client, $old);

        $this->failingGateway();

        try {
            $this->service()->upgrade($client, Plan::factory()->create(['storage_amount' => 1, 'storage_unit' => 'GB']));
            $this->fail('Expected InvalidStateTransition.');
        } catch (InvalidStateTransition) {
            // expected
        }

        $this->assertSame($old->id, $client->subscription()->first()->plan_id);
    }

    // ---- Cancel + grace sweep -----------------------------------------------------

    public function test_cancelling_sets_a_grace_period_and_notifies(): void
    {
        config(['billing.grace_days' => 7]);
        $client = User::factory()->create();
        $this->service()->subscribe($client, Plan::factory()->create());

        $subscription = $this->service()->cancel($client);

        $this->assertSame('cancelled', $subscription->status->value);
        $this->assertTrue($subscription->grace_ends_at->isSameDay(now()->addDays(7)));
        $this->assertTrue($subscription->inGrace());
    }

    public function test_cancelling_an_already_cancelled_subscription_fails(): void
    {
        $client = User::factory()->create();
        $this->service()->subscribe($client, Plan::factory()->create());
        $this->service()->cancel($client);

        $this->expectException(InvalidStateTransition::class);
        $this->service()->cancel($client);
    }

    public function test_the_grace_sweep_restricts_only_expired_subscriptions(): void
    {
        Notification::fake();

        $expired = User::factory()->create();
        $sub1 = $this->service()->subscribe($expired, Plan::factory()->create());
        $this->service()->cancel($expired);
        $sub1->fresh()->update(['grace_ends_at' => now()->subDay()]);

        $stillInGrace = User::factory()->create();
        $this->service()->subscribe($stillInGrace, Plan::factory()->create());
        $this->service()->cancel($stillInGrace);

        $count = $this->service()->expireOverdueGracePeriods();

        $this->assertSame(1, $count);
        $this->assertSame('restricted', $expired->subscription()->first()->status->value);
        $this->assertSame('cancelled', $stillInGrace->subscription()->first()->status->value);
        Notification::assertSentTo($expired, SubscriptionRestricted::class);
        Notification::assertNotSentTo($stillInGrace, SubscriptionRestricted::class);
    }

    // ---- Downgrade file-hiding ------------------------------------------------------

    public function test_downgrade_hides_oldest_files_first_until_it_fits(): void
    {
        $quota = app(StorageQuotaService::class);
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();
        $big = Plan::factory()->create(['storage_amount' => 10, 'storage_unit' => 'MB']);
        $small = Plan::factory()->create(['storage_amount' => 2, 'storage_unit' => 'MB']);
        $this->service()->subscribe($client, $big);

        $case = LegalCase::create(['client_id' => $client->user_id, 'advocate_id' => $lawyer->user_id, 'title' => 'x', 'status' => CaseStatus::Accepted]);
        $mb = 1024 * 1024;
        $doc1 = CaseDocument::create(['case_id' => $case->id, 'uploaded_by' => $client->user_id, 'path' => 'a', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => $mb]);
        $doc2 = CaseDocument::create(['case_id' => $case->id, 'uploaded_by' => $client->user_id, 'path' => 'b', 'original_name' => 'b.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => $mb]);
        $doc3 = CaseDocument::create(['case_id' => $case->id, 'uploaded_by' => $client->user_id, 'path' => 'c', 'original_name' => 'c.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => $mb]);

        $this->service()->downgrade($client, $small);

        $this->assertNotNull($doc1->fresh()->inaccessible_at, 'oldest file should be hidden');
        $this->assertNull($doc2->fresh()->inaccessible_at);
        $this->assertNull($doc3->fresh()->inaccessible_at, 'newest file must stay accessible');
        $this->assertSame(2 * $mb, $quota->usedBytes($client));
    }

    public function test_downgrade_that_still_fits_hides_nothing(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();
        $big = Plan::factory()->create(['storage_amount' => 10, 'storage_unit' => 'MB']);
        $small = Plan::factory()->create(['storage_amount' => 5, 'storage_unit' => 'MB']);
        $this->service()->subscribe($client, $big);

        $case = LegalCase::create(['client_id' => $client->user_id, 'advocate_id' => $lawyer->user_id, 'title' => 'x', 'status' => CaseStatus::Accepted]);
        $doc = CaseDocument::create(['case_id' => $case->id, 'uploaded_by' => $client->user_id, 'path' => 'a', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024 * 1024]);

        $this->service()->downgrade($client, $small);

        $this->assertNull($doc->fresh()->inaccessible_at);
    }

    // ---- Scheduled command ----------------------------------------------------------

    public function test_the_scheduled_command_runs_the_sweep(): void
    {
        $client = User::factory()->create();
        $this->service()->subscribe($client, Plan::factory()->create());
        $this->service()->cancel($client);
        $client->subscription->update(['grace_ends_at' => now()->subDay()]);

        $this->artisan('subscriptions:expire-grace-periods')
            ->expectsOutputToContain('1 subscription(s) restricted.')
            ->assertSuccessful();

        $this->assertSame('restricted', $client->subscription()->first()->status->value);
    }
}

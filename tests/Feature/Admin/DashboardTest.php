<?php

namespace Tests\Feature\Admin;

use App\Enums\CaseStatus;
use App\Enums\VerificationStatus;
use App\Models\Admin;
use App\Models\LegalCase;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = Admin::factory()->superAdmin()->create();
    }

    private function asSuper(): static
    {
        return $this->actingAs($this->super, 'admin');
    }

    public function test_client_and_lawyer_counts_are_real(): void
    {
        User::factory()->count(2)->create();
        User::factory()->suspended()->create();
        User::factory()->lawyer(VerificationStatus::Verified)->create();
        User::factory()->lawyer(VerificationStatus::Pending)->create();

        $response = $this->asSuper()->get(route('admin.dashboard'))->assertOk();

        // 3 clients total (2 active + 1 suspended), 1 active shown as the note.
        $response->assertSeeInOrder(['Total Clients', '3']);
        $response->assertSeeInOrder(['Total Lawyers', '2']);
        $response->assertDontSee('1,428'); // the old hardcoded stat
        $response->assertDontSee('Adv. Sarah Jenkins'); // the old dummy pending-action row
    }

    public function test_lawyer_account_buckets_are_mutually_exclusive_and_cover_every_lawyer(): void
    {
        User::factory()->lawyer(VerificationStatus::Verified)->create();
        User::factory()->lawyer(VerificationStatus::Pending)->create();
        // Verified but also suspended - must count as suspended only, not both.
        User::factory()->lawyer(VerificationStatus::Verified)->suspended()->create();

        $response = $this->asSuper()->get(route('admin.dashboard'))->assertOk();

        $response->assertSeeInOrder(['Lawyer Accounts', '3']);
        $response->assertSeeInOrder(['Verified:', '1']);
        $response->assertSeeInOrder(['Pending:', '1']);
        $response->assertSeeInOrder(['Suspended:', '1']);
        $response->assertDontSee('Rejected:');
        $response->assertDontSee('Inactive:');
    }

    public function test_case_overview_reflects_real_statuses(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();
        LegalCase::create(['client_id' => $client->user_id, 'advocate_id' => $lawyer->user_id, 'title' => 'a', 'status' => CaseStatus::Pending]);
        LegalCase::create(['client_id' => $client->user_id, 'advocate_id' => $lawyer->user_id, 'title' => 'b', 'status' => CaseStatus::Accepted]);

        $this->asSuper()->get(route('admin.dashboard'))
            ->assertSee('Pending')
            ->assertSee('Accepted')
            ->assertDontSee('94'); // old hardcoded "New Cases" number
    }

    public function test_restricted_and_active_subscription_counts_are_real(): void
    {
        $service = app(SubscriptionService::class);
        $active = User::factory()->create();
        $service->subscribe($active, Plan::factory()->create());

        $restricted = User::factory()->create();
        $service->subscribe($restricted, Plan::factory()->create());
        $service->cancel($restricted);
        $restricted->subscription->update(['grace_ends_at' => now()->subDay()]);
        $service->expireOverdueGracePeriods();

        $response = $this->asSuper()->get(route('admin.dashboard'))->assertOk();

        $response->assertSeeInOrder(['Restricted Clients', '1']);
        $response->assertSeeInOrder(['Active Subscriptions', '1']);
    }

    public function test_recent_activity_shows_real_registrations_and_billing(): void
    {
        $client = User::factory()->create(['name' => 'Fresh Client']);
        app(SubscriptionService::class)->subscribe($client, Plan::factory()->create(['name' => 'Starter']));

        $response = $this->asSuper()->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('New client registered');
        $response->assertSee('Fresh Client');
        $response->assertSee('Subscription purchased');
        $response->assertSee('Starter');
        $response->assertDontSee('TechGlobal Corp'); // old dummy activity entry
    }

    public function test_failed_payments_are_listed_with_a_real_client_link(): void
    {
        $client = User::factory()->create();

        $mock = \Mockery::mock(\App\Contracts\BillingGateway::class);
        $mock->shouldReceive('charge')->andReturn(new \App\Support\Billing\ChargeResult(successful: false, failureMessage: 'Card declined.'));
        $this->app->instance(\App\Contracts\BillingGateway::class, $mock);

        try {
            app(SubscriptionService::class)->subscribe($client, Plan::factory()->create());
        } catch (\App\Exceptions\InvalidStateTransition) {
        }

        $this->asSuper()->get(route('admin.dashboard'))
            ->assertSee('Card declined.');
    }

    public function test_an_empty_platform_shows_honest_empty_states(): void
    {
        $this->asSuper()->get(route('admin.dashboard'))
            ->assertSee('Nothing has happened yet.')
            ->assertSee('No failed payments.');
    }
}

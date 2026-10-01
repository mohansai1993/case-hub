<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The real "Subscription" card on a client's details page (replaces the old static subscription-details page). */
class ClientSubscriptionCardTest extends TestCase
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

    public function test_a_client_with_no_plan_shows_an_honest_empty_state(): void
    {
        $client = User::factory()->create();

        $this->asSuper()->get(route('admin.client-details', $client->user_id))
            ->assertSee('This client has not subscribed to a storage plan yet.');
    }

    public function test_a_subscribed_clients_plan_and_usage_are_shown(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();
        $plan = Plan::factory()->create(['name' => 'Standard', 'storage_amount' => 1, 'storage_unit' => 'MB', 'price' => 2000]);
        app(SubscriptionService::class)->subscribe($client, $plan);

        $case = LegalCase::create(['client_id' => $client->user_id, 'advocate_id' => $lawyer->user_id, 'title' => 'x', 'status' => 'accepted']);
        CaseDocument::create(['case_id' => $case->id, 'uploaded_by' => $client->user_id, 'path' => 'a', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 512 * 1024]);

        $response = $this->asSuper()->get(route('admin.client-details', $client->user_id))->assertOk();

        $response->assertSee('Standard (1 MB)');
        $response->assertSee('Active');
        $response->assertSee('0.5 MB'); // 512 KB of the 1 MB plan
        $response->assertDontSee('Rahul Sharma'); // the old static mock's client
    }

    public function test_recent_billing_history_is_shown(): void
    {
        $client = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 1500]);
        app(SubscriptionService::class)->subscribe($client, $plan);

        $this->asSuper()->get(route('admin.client-details', $client->user_id))
            ->assertSee('Subscribe')
            ->assertSee('Succeeded');
    }

    public function test_a_restricted_clients_status_is_shown_in_red(): void
    {
        $client = User::factory()->create();
        $service = app(SubscriptionService::class);
        $service->subscribe($client, Plan::factory()->create());
        $service->cancel($client);
        $client->subscription->update(['grace_ends_at' => now()->subDay()]);
        $service->expireOverdueGracePeriods();

        $this->asSuper()->get(route('admin.client-details', $client->user_id))
            ->assertSee('Restricted');
    }
}

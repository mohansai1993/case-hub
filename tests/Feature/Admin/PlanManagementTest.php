<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanManagementTest extends TestCase
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

    private function asStaff(array $permissions = []): static
    {
        return $this->actingAs(Admin::factory()->withPermissions($permissions)->create(), 'admin');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Standard',
            'storage_amount' => 1,
            'storage_unit' => 'GB',
            'price' => 199,
            'description' => 'Expanded capacity for corporate files.',
            'is_popular' => false,
        ], $overrides);
    }

    // ---- Access -------------------------------------------------------------

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.subscriptions'))->assertRedirect(route('login'));
        $this->postJson(route('admin.plans.store'), [])->assertUnauthorized();
    }

    public function test_managing_plans_requires_the_manage_permission(): void
    {
        $plan = Plan::factory()->create();

        $this->asStaff(['subscriptions.view'])->get(route('admin.create-plan'))->assertForbidden();
        $this->asStaff(['subscriptions.view'])->postJson(route('admin.plans.store'), $this->validPayload())->assertForbidden();
        $this->asStaff(['subscriptions.view'])->postJson(route('admin.plans.toggle', $plan))->assertForbidden();
        $this->asStaff(['subscriptions.manage'])->postJson(route('admin.plans.store'), $this->validPayload())->assertCreated();
    }

    // ---- List -----------------------------------------------------------------

    public function test_subscriptions_page_lists_plans(): void
    {
        Plan::factory()->create(['name' => 'Enterprise Tier']);

        $this->asSuper()->get(route('admin.subscriptions'))->assertSee('Enterprise Tier');
    }

    // ---- Create -----------------------------------------------------------------

    public function test_a_plan_can_be_created_in_mb_or_gb(): void
    {
        $response = $this->asSuper()->postJson(route('admin.plans.store'), $this->validPayload([
            'name' => 'Basic', 'storage_amount' => 500, 'storage_unit' => 'MB',
        ]))->assertCreated();

        $plan = Plan::firstWhere('name', 'Basic');
        $this->assertSame(500, $plan->storage_amount);
        $this->assertSame('MB', $plan->storage_unit->value);
        $this->assertSame('500 MB', $plan->storageLabel());
        $response->assertJsonPath('data.id', $plan->id);
    }

    public function test_plan_name_must_be_unique(): void
    {
        Plan::factory()->create(['name' => 'Standard']);

        $this->asSuper()->postJson(route('admin.plans.store'), $this->validPayload(['name' => 'Standard']))
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_storage_unit_must_be_mb_or_gb(): void
    {
        $this->asSuper()->postJson(route('admin.plans.store'), $this->validPayload(['storage_unit' => 'TB']))
            ->assertUnprocessable()->assertJsonValidationErrors('storage_unit');
    }

    public function test_only_one_plan_can_be_popular_at_a_time(): void
    {
        $existing = Plan::factory()->popular()->create();

        $this->asSuper()->postJson(route('admin.plans.store'), $this->validPayload(['is_popular' => true]))->assertCreated();

        $this->assertFalse($existing->fresh()->is_popular);
        $this->assertTrue(Plan::firstWhere('name', 'Standard')->is_popular);
    }

    // ---- Update -----------------------------------------------------------------

    public function test_a_plan_can_be_updated(): void
    {
        $plan = Plan::factory()->create(['name' => 'Old', 'price' => 99]);

        $this->asSuper()->putJson(route('admin.plans.update', $plan), $this->validPayload(['name' => 'New', 'price' => 299]))
            ->assertOk();

        $plan->refresh();
        $this->assertSame('New', $plan->name);
        $this->assertSame(299, $plan->price);
    }

    public function test_marking_a_plan_popular_on_update_unsets_other_plans(): void
    {
        $other = Plan::factory()->popular()->create();
        $plan = Plan::factory()->create();

        $this->asSuper()->putJson(route('admin.plans.update', $plan), $this->validPayload(['is_popular' => true]))->assertOk();

        $this->assertFalse($other->fresh()->is_popular);
        $this->assertTrue($plan->fresh()->is_popular);
    }

    // ---- Toggle / bulk deactivate -------------------------------------------

    public function test_a_plan_can_be_activated_and_deactivated(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        $this->asSuper()->postJson(route('admin.plans.toggle', $plan))
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertFalse($plan->fresh()->is_active);

        $this->asSuper()->postJson(route('admin.plans.toggle', $plan))
            ->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_plans_can_be_bulk_deactivated(): void
    {
        $plans = Plan::factory()->count(3)->create(['is_active' => true]);

        $this->asSuper()->postJson(route('admin.plans.bulk-deactivate'), ['ids' => $plans->pluck('id')->take(2)->all()])
            ->assertOk();

        $this->assertSame(2, Plan::where('is_active', false)->count());
    }

    // ---- Delete -----------------------------------------------------------------

    public function test_a_plan_can_be_deleted(): void
    {
        $plan = Plan::factory()->create();

        $this->asSuper()->deleteJson(route('admin.plans.destroy', $plan))->assertOk();

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }
}

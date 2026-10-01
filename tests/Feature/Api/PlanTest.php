<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\User;

class PlanTest extends ApiTestCase
{
    private const INDEX = '/api/v1/plans';

    public function test_guests_cannot_list_plans(): void
    {
        $this->getJson(self::INDEX)->assertStatus(401);
    }

    public function test_lawyers_cannot_list_plans(): void
    {
        $lawyer = User::factory()->lawyer()->create();

        $this->getJson(self::INDEX, $this->bearer($lawyer))
            ->assertForbidden()
            ->assertJsonPath('message', 'Only clients can view subscription plans.');
    }

    public function test_a_client_sees_only_active_plans_popular_first(): void
    {
        $client = User::factory()->create();
        Plan::factory()->create(['name' => 'Basic', 'storage_amount' => 500, 'storage_unit' => 'MB', 'price' => 99]);
        Plan::factory()->popular()->create(['name' => 'Standard', 'storage_amount' => 1, 'storage_unit' => 'GB', 'price' => 199]);
        Plan::factory()->inactive()->create(['name' => 'Retired Plan']);

        $response = $this->getJson(self::INDEX, $this->bearer($client))->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'Standard');
        $response->assertJsonPath('data.0.storage', '1 GB');
        $response->assertJsonPath('data.0.is_popular', true);
        $response->assertJsonPath('data.1.name', 'Basic');
        $response->assertJsonPath('data.1.storage', '500 MB');
        $response->assertDontSee('Retired Plan');
    }
}

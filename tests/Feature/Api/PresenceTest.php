<?php

namespace Tests\Feature\Api;

use App\Enums\CaseStatus;
use App\Enums\VerificationStatus;
use App\Models\LegalCase;
use App\Models\User;

class PresenceTest extends ApiTestCase
{
    public function test_an_authenticated_request_touches_last_active_at(): void
    {
        $client = User::factory()->create();
        $this->assertNull($client->last_active_at);

        $this->getJson('/api/v1/auth/me', $this->bearer($client))->assertOk();

        $this->assertNotNull($client->fresh()->last_active_at);
    }

    public function test_touching_last_active_is_throttled(): void
    {
        $client = User::factory()->create();

        $this->travelTo(now());
        $this->getJson('/api/v1/auth/me', $this->bearer($client))->assertOk();
        $first = $client->fresh()->last_active_at;

        // Well inside the 60s throttle window - should not move.
        $this->travelTo(now()->addSeconds(10));
        $this->getJson('/api/v1/auth/me', $this->bearer($client))->assertOk();
        $this->assertTrue($first->eq($client->fresh()->last_active_at));

        // Past the throttle window - should move.
        $this->travelTo(now()->addSeconds(61));
        $this->getJson('/api/v1/auth/me', $this->bearer($client))->assertOk();
        $this->assertTrue($client->fresh()->last_active_at->gt($first));

        $this->travelBack();
    }

    public function test_user_is_online_only_within_the_threshold(): void
    {
        $user = User::factory()->create(['last_active_at' => now()]);
        $this->assertTrue($user->isOnline());

        $user->forceFill(['last_active_at' => now()->subMinutes(5)])->save();
        $this->assertFalse($user->fresh()->isOnline());

        $user->forceFill(['last_active_at' => null])->save();
        $this->assertFalse($user->fresh()->isOnline());
    }

    public function test_case_resource_exposes_presence_for_both_participants(): void
    {
        $client = User::factory()->create(['last_active_at' => now()]);
        $advocate = User::factory()->lawyer()->create(['last_active_at' => now()->subMinutes(10)]);
        $case = LegalCase::create([
            'client_id' => $client->user_id,
            'advocate_id' => $advocate->user_id,
            'title' => 'Property dispute',
            'status' => CaseStatus::Accepted,
            'submitted_at' => now(),
        ]);

        $this->getJson("/api/v1/cases/{$case->id}", $this->bearer($client))
            ->assertOk()
            ->assertJsonPath('data.client.is_online', true)
            ->assertJsonPath('data.advocate.is_online', false)
            ->assertJsonPath('data.advocate.last_active_at', fn ($value) => $value !== null);
    }

    public function test_lawyer_directory_does_not_expose_presence(): void
    {
        $client = User::factory()->create();
        User::factory()->lawyer(VerificationStatus::Verified)->create(['last_active_at' => now()]);

        $response = $this->getJson('/api/v1/lawyers', $this->bearer($client))->assertOk();

        $this->assertArrayNotHasKey('is_online', $response->json('data.0'));
        $this->assertArrayNotHasKey('last_active_at', $response->json('data.0'));
    }
}

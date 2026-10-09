<?php

namespace Tests\Feature\Api;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CaseDraftCleanupTest extends ApiTestCase
{
    private function givePlan(User $client): void
    {
        app(SubscriptionService::class)->subscribe($client, Plan::factory()->create());
    }

    public function test_an_old_unsubmitted_draft_and_its_evidence_are_purged(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $this->givePlan($client);

        $draft = $this->postJson('/api/v1/cases', ['title' => 'x'], $this->bearer($client))->json('data.id');
        $this->postJson("/api/v1/cases/{$draft}/documents", [
            'file' => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
        ], $this->bearer($client))->assertCreated();

        $path = CaseDocument::firstWhere('case_id', $draft)->path;
        Storage::disk('local')->assertExists($path);

        LegalCase::whereKey($draft)->update(['created_at' => now()->subHours(49)]);

        $this->artisan('cases:purge-abandoned-drafts')
            ->expectsOutputToContain('1 abandoned draft case(s) purged.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('cases', ['id' => $draft]);
        $this->assertDatabaseCount('case_documents', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_a_recent_draft_is_left_alone(): void
    {
        $client = User::factory()->create();
        $this->givePlan($client);
        $draft = $this->postJson('/api/v1/cases', ['title' => 'x'], $this->bearer($client))->json('data.id');

        $this->artisan('cases:purge-abandoned-drafts')
            ->expectsOutputToContain('0 abandoned draft case(s) purged.')
            ->assertSuccessful();

        $this->assertDatabaseHas('cases', ['id' => $draft]);
    }

    public function test_a_submitted_case_is_never_purged_no_matter_how_old(): void
    {
        $client = User::factory()->create();
        $this->givePlan($client);
        $draft = $this->postJson('/api/v1/cases', ['title' => 'x'], $this->bearer($client))->json('data.id');

        $area = \App\Models\PracticeArea::create(['slug' => 'x', 'name' => 'x', 'is_active' => true]);
        $this->postJson("/api/v1/cases/{$draft}/submit", [
            'title' => 'x', 'practice_area_id' => $area->id,
            'description' => 'x', 'incident_date' => '2026-01-01', 'location' => 'x',
        ], $this->bearer($client))->assertOk();

        LegalCase::whereKey($draft)->update(['created_at' => now()->subDays(30)]);

        $this->artisan('cases:purge-abandoned-drafts')->assertSuccessful();

        $this->assertDatabaseHas('cases', ['id' => $draft]);
    }
}

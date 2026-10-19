<?php

namespace Tests\Feature\Api;

use App\Enums\CaseStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\LegalCase;
use App\Models\PracticeArea;
use App\Models\User;

class LawyerDirectoryTest extends ApiTestCase
{
    private function verifiedLawyer(string $name, ?PracticeArea $area = null): User
    {
        $lawyer = User::factory()->lawyer(VerificationStatus::Verified)->create(['name' => $name]);

        if ($area) {
            $lawyer->practiceAreas()->attach($area->id);
        }

        return $lawyer;
    }

    public function test_directory_only_lists_verified_active_lawyers(): void
    {
        $client = User::factory()->create();
        $matched = $this->verifiedLawyer('Adv. Sarah Jenkins');

        $unverified = User::factory()->lawyer(VerificationStatus::Pending)->create();
        $suspended = $this->verifiedLawyer('Suspended Lawyer');
        $suspended->forceFill(['status' => UserStatus::Suspended])->save();

        $this->getJson('/api/v1/lawyers', $this->bearer($client))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matched->user_id);
    }

    public function test_directory_can_be_searched_by_name(): void
    {
        $client = User::factory()->create();
        $this->verifiedLawyer('Adv. Sarah Jenkins');
        $this->verifiedLawyer('Adv. James Wilson');

        $this->getJson('/api/v1/lawyers?q=Sarah', $this->bearer($client))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Adv. Sarah Jenkins');
    }

    public function test_directory_can_be_filtered_by_practice_area(): void
    {
        $client = User::factory()->create();
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $otherArea = PracticeArea::create(['slug' => 'tax', 'name' => 'Tax', 'is_active' => true]);

        $matched = $this->verifiedLawyer('IP Lawyer', $area);
        $this->verifiedLawyer('Tax Lawyer', $otherArea);

        $this->getJson("/api/v1/lawyers?practice_area_id={$area->id}", $this->bearer($client))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matched->user_id);
    }

    public function test_only_clients_can_browse_the_lawyer_directory(): void
    {
        $lawyer = User::factory()->lawyer(VerificationStatus::Verified)->create();

        $this->getJson('/api/v1/lawyers', $this->bearer($lawyer))->assertForbidden();
    }

    // ---- Lawyer detail / profile ---------------------------------------------------

    public function test_client_can_view_a_verified_lawyers_profile(): void
    {
        $client = User::factory()->create();
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $lawyer = $this->verifiedLawyer('Adv. Sarah Jenkins', $area);

        $this->getJson("/api/v1/lawyers/{$lawyer->user_id}", $this->bearer($client))
            ->assertOk()
            ->assertJsonPath('data.id', $lawyer->user_id)
            ->assertJsonPath('data.name', 'Adv. Sarah Jenkins')
            ->assertJsonPath('data.lawyer.verification_status', 'verified')
            ->assertJsonPath('data.lawyer.practice_areas.0.name', 'IP Rights')
            ->assertJsonPath('data.lawyer.completed_cases', 0);
    }

    public function test_profile_counts_only_closed_cases_for_this_lawyer(): void
    {
        $client = User::factory()->create();
        $lawyer = $this->verifiedLawyer('Adv. Sarah Jenkins');
        $otherLawyer = $this->verifiedLawyer('Adv. James Wilson');

        LegalCase::create(['client_id' => $client->user_id, 'advocate_id' => $lawyer->user_id, 'status' => CaseStatus::Closed, 'submitted_at' => now()]);
        LegalCase::create(['client_id' => $client->user_id, 'advocate_id' => $lawyer->user_id, 'status' => CaseStatus::Closed, 'submitted_at' => now()]);
        LegalCase::create(['client_id' => $client->user_id, 'advocate_id' => $lawyer->user_id, 'status' => CaseStatus::Accepted, 'submitted_at' => now()]);
        LegalCase::create(['client_id' => $client->user_id, 'advocate_id' => $otherLawyer->user_id, 'status' => CaseStatus::Closed, 'submitted_at' => now()]);

        $this->getJson("/api/v1/lawyers/{$lawyer->user_id}", $this->bearer($client))
            ->assertOk()
            ->assertJsonPath('data.lawyer.completed_cases', 2);
    }

    public function test_unverified_or_suspended_lawyer_profile_is_not_found(): void
    {
        $client = User::factory()->create();
        $unverified = User::factory()->lawyer(VerificationStatus::Pending)->create();
        $suspended = $this->verifiedLawyer('Suspended Lawyer');
        $suspended->forceFill(['status' => UserStatus::Suspended])->save();

        $this->getJson("/api/v1/lawyers/{$unverified->user_id}", $this->bearer($client))->assertNotFound();
        $this->getJson("/api/v1/lawyers/{$suspended->user_id}", $this->bearer($client))->assertNotFound();
    }

    public function test_a_clients_own_id_through_this_endpoint_is_not_found(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();

        $this->getJson("/api/v1/lawyers/{$otherClient->user_id}", $this->bearer($client))->assertNotFound();
    }

    public function test_only_clients_can_view_a_lawyer_profile(): void
    {
        $lawyer = $this->verifiedLawyer('Adv. Sarah Jenkins');
        $viewer = $this->verifiedLawyer('Adv. James Wilson');

        $this->getJson("/api/v1/lawyers/{$lawyer->user_id}", $this->bearer($viewer))->assertForbidden();
    }
}

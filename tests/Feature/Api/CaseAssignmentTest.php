<?php

namespace Tests\Feature\Api;

use App\Enums\CaseStatus;
use App\Enums\VerificationStatus;
use App\Models\CaseAssignmentRequest;
use App\Models\LegalCase;
use App\Models\PracticeArea;
use App\Models\User;

class CaseAssignmentTest extends ApiTestCase
{
    private function submittedCase(User $client, PracticeArea $area): LegalCase
    {
        return LegalCase::create([
            'client_id' => $client->user_id,
            'advocate_id' => null,
            'title' => 'Wrongful termination',
            'practice_area_id' => $area->id,
            'status' => CaseStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    private function verifiedLawyer(PracticeArea $area): User
    {
        $lawyer = User::factory()->lawyer(VerificationStatus::Verified)->create();
        $lawyer->practiceAreas()->attach($area->id);

        return $lawyer;
    }

    // ---- Browsing lawyers -------------------------------------------------------------

    public function test_lawyer_list_only_shows_verified_active_lawyers_in_the_cases_practice_area(): void
    {
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $otherArea = PracticeArea::create(['slug' => 'tax', 'name' => 'Tax', 'is_active' => true]);
        $client = User::factory()->create();
        $case = $this->submittedCase($client, $area);

        $matched = $this->verifiedLawyer($area);

        $wrongArea = $this->verifiedLawyer($otherArea);

        $unverified = User::factory()->lawyer(VerificationStatus::Pending)->create();
        $unverified->practiceAreas()->attach($area->id);

        $suspended = $this->verifiedLawyer($area);
        $suspended->forceFill(['status' => \App\Enums\UserStatus::Suspended])->save();

        $this->getJson("/api/v1/cases/{$case->id}/lawyers", $this->bearer($client))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matched->user_id);
    }

    public function test_browsing_lawyers_requires_case_ownership(): void
    {
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $client = User::factory()->create();
        $other = User::factory()->create();
        $case = $this->submittedCase($client, $area);

        $this->getJson("/api/v1/cases/{$case->id}/lawyers", $this->bearer($other))->assertForbidden();
    }

    public function test_browsing_lawyers_requires_a_submitted_case(): void
    {
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $client = User::factory()->create();
        $draft = LegalCase::create([
            'client_id' => $client->user_id,
            'practice_area_id' => $area->id,
            'status' => CaseStatus::Pending,
            'submitted_at' => null,
        ]);

        $this->getJson("/api/v1/cases/{$draft->id}/lawyers", $this->bearer($client))->assertStatus(422);
    }

    // ---- Assigning ----------------------------------------------------------------------

    public function test_a_client_can_assign_a_lawyer_which_creates_a_pending_request(): void
    {
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $client = User::factory()->create();
        $case = $this->submittedCase($client, $area);
        $lawyer = $this->verifiedLawyer($area);

        $this->postJson("/api/v1/cases/{$case->id}/assignment-requests", ['lawyer_id' => $lawyer->user_id], $this->bearer($client))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.lawyer.id', $lawyer->user_id);

        $this->assertDatabaseHas('case_assignment_requests', [
            'case_id' => $case->id,
            'lawyer_id' => $lawyer->user_id,
            'requested_by' => $client->user_id,
            'status' => 'pending',
        ]);

        // Not assigned yet - that only happens once an admin approves.
        $this->assertNull($case->fresh()->advocate_id);
        $this->assertSame(CaseStatus::Pending, $case->fresh()->status);
    }

    public function test_assigning_a_lawyer_outside_the_practice_area_is_rejected(): void
    {
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $otherArea = PracticeArea::create(['slug' => 'tax', 'name' => 'Tax', 'is_active' => true]);
        $client = User::factory()->create();
        $case = $this->submittedCase($client, $area);
        $lawyer = $this->verifiedLawyer($otherArea);

        $this->postJson("/api/v1/cases/{$case->id}/assignment-requests", ['lawyer_id' => $lawyer->user_id], $this->bearer($client))
            ->assertStatus(422);

        $this->assertDatabaseCount('case_assignment_requests', 0);
    }

    public function test_a_second_pending_request_is_rejected_while_one_is_already_awaiting_review(): void
    {
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $client = User::factory()->create();
        $case = $this->submittedCase($client, $area);
        $first = $this->verifiedLawyer($area);
        $second = $this->verifiedLawyer($area);

        $this->postJson("/api/v1/cases/{$case->id}/assignment-requests", ['lawyer_id' => $first->user_id], $this->bearer($client))
            ->assertCreated();

        $this->postJson("/api/v1/cases/{$case->id}/assignment-requests", ['lawyer_id' => $second->user_id], $this->bearer($client))
            ->assertStatus(422)
            ->assertJsonPath('message', 'A request for this case is already awaiting admin review.');

        $this->assertDatabaseCount('case_assignment_requests', 1);
    }

    public function test_assigning_fails_once_the_case_already_has_an_advocate(): void
    {
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $client = User::factory()->create();
        $existingAdvocate = $this->verifiedLawyer($area);
        $case = $this->submittedCase($client, $area);
        $case->update(['advocate_id' => $existingAdvocate->user_id, 'status' => CaseStatus::Assigned]);
        $lawyer = $this->verifiedLawyer($area);

        $this->postJson("/api/v1/cases/{$case->id}/assignment-requests", ['lawyer_id' => $lawyer->user_id], $this->bearer($client))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This case already has a lawyer assigned.');
    }

    public function test_a_client_can_pick_again_after_a_prior_request_was_rejected(): void
    {
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $client = User::factory()->create();
        $case = $this->submittedCase($client, $area);
        $rejectedPick = $this->verifiedLawyer($area);
        CaseAssignmentRequest::create([
            'case_id' => $case->id,
            'lawyer_id' => $rejectedPick->user_id,
            'requested_by' => $client->user_id,
            'status' => 'rejected',
            'reason' => 'Not a fit',
            'decided_at' => now(),
        ]);

        $newPick = $this->verifiedLawyer($area);

        $this->postJson("/api/v1/cases/{$case->id}/assignment-requests", ['lawyer_id' => $newPick->user_id], $this->bearer($client))
            ->assertCreated();

        $this->assertDatabaseCount('case_assignment_requests', 2);
    }

    public function test_only_the_owning_client_can_assign_a_lawyer(): void
    {
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $client = User::factory()->create();
        $other = User::factory()->create();
        $case = $this->submittedCase($client, $area);
        $lawyer = $this->verifiedLawyer($area);

        $this->postJson("/api/v1/cases/{$case->id}/assignment-requests", ['lawyer_id' => $lawyer->user_id], $this->bearer($other))
            ->assertForbidden();
    }
}

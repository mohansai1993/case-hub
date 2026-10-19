<?php

namespace Tests\Feature\Admin;

use App\Enums\CaseStatus;
use App\Enums\VerificationStatus;
use App\Models\Admin;
use App\Models\CaseAssignmentRequest;
use App\Models\LegalCase;
use App\Models\PracticeArea;
use App\Models\User;
use App\Notifications\CaseAssignmentApproved;
use App\Notifications\CaseAssignmentRejected;
use App\Notifications\LawyerCaseAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CaseAssignmentModerationTest extends TestCase
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

    private function asStaff(array $permissions): static
    {
        return $this->actingAs(Admin::factory()->withPermissions($permissions)->create(), 'admin');
    }

    /** A submitted case, a verified lawyer serving its practice area, and a pending request pairing them. */
    private function pendingRequest(): CaseAssignmentRequest
    {
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer(VerificationStatus::Verified)->create();
        $lawyer->practiceAreas()->attach($area->id);

        $case = LegalCase::create([
            'client_id' => $client->user_id,
            'advocate_id' => null,
            'title' => 'Wrongful termination',
            'practice_area_id' => $area->id,
            'status' => CaseStatus::Pending,
            'submitted_at' => now(),
        ]);

        return CaseAssignmentRequest::create([
            'case_id' => $case->id,
            'lawyer_id' => $lawyer->user_id,
            'requested_by' => $client->user_id,
            'status' => 'pending',
        ]);
    }

    // ---- Permissions --------------------------------------------------------------

    public function test_listing_requires_the_view_permission(): void
    {
        $this->pendingRequest();

        $this->asStaff(['clients.view'])->getJson(route('admin.case-assignment-requests'))->assertForbidden();
        $this->asStaff(['cases.view'])->getJson(route('admin.case-assignment-requests'))->assertOk();
    }

    public function test_the_list_page_shows_pending_requests_and_the_detail_page_offers_decisions(): void
    {
        $request = $this->pendingRequest();

        $this->asSuper()->get(route('admin.case-assignment-requests'))
            ->assertOk()
            ->assertSee($request->case->caseNumber())
            ->assertSee($request->lawyer->name);

        $this->asSuper()->get(route('admin.case-assignment-request-details', $request->id))
            ->assertOk()
            ->assertSee($request->case->title)
            ->assertSee($request->lawyer->name)
            ->assertSee('data-moderate="approve-case-assignment"', false)
            ->assertSee('data-moderate="reject-case-assignment"', false);
    }

    public function test_case_management_appears_in_the_sidebar_only_with_the_view_permission(): void
    {
        $this->asSuper()->get(route('admin.dashboard'))->assertSee('Case Management');
        $this->asStaff(['clients.view'])->get(route('admin.clients'))->assertDontSee('Case Management');
    }

    public function test_detail_page_uses_sweetalert_and_no_native_dialogs(): void
    {
        $request = $this->pendingRequest();

        $this->asSuper()->get(route('admin.case-assignment-request-details', $request->id))
            ->assertSee('sweetalert2', false);
    }

    public function test_approving_and_rejecting_require_the_update_permission(): void
    {
        $request = $this->pendingRequest();

        $this->asStaff(['cases.view'])
            ->postJson(route('admin.case-assignment-requests.approve', $request->id))
            ->assertForbidden();
        $this->asStaff(['cases.view'])
            ->postJson(route('admin.case-assignment-requests.reject', $request->id), ['reason' => 'because'])
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status->value);
    }

    // ---- Approve --------------------------------------------------------------------

    public function test_approving_assigns_the_lawyer_and_notifies_everyone(): void
    {
        Notification::fake();
        $request = $this->pendingRequest();

        $this->asSuper()->postJson(route('admin.case-assignment-requests.approve', $request->id))
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $fresh = $request->fresh();
        $this->assertSame('approved', $fresh->status->value);
        $this->assertSame($this->super->id, $fresh->admin_id);
        $this->assertNotNull($fresh->decided_at);

        $case = $fresh->case;
        $this->assertSame($fresh->lawyer_id, $case->advocate_id);
        $this->assertSame(CaseStatus::Assigned, $case->status);

        Notification::assertSentTo($fresh->lawyer, LawyerCaseAssigned::class);
        Notification::assertSentTo($fresh->client, CaseAssignmentApproved::class);
    }

    public function test_approving_twice_is_rejected_cleanly(): void
    {
        $request = $this->pendingRequest();

        $this->asSuper()->postJson(route('admin.case-assignment-requests.approve', $request->id))->assertOk();
        $this->asSuper()->postJson(route('admin.case-assignment-requests.approve', $request->id))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This request has already been decided.');
    }

    public function test_approving_fails_if_the_case_already_has_an_advocate(): void
    {
        $request = $this->pendingRequest();
        $request->case->update(['advocate_id' => $request->lawyer_id, 'status' => CaseStatus::Assigned]);

        $this->asSuper()->postJson(route('admin.case-assignment-requests.approve', $request->id))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This case already has a lawyer assigned.');

        $this->assertSame('pending', $request->fresh()->status->value);
    }

    // ---- Reject ---------------------------------------------------------------------

    public function test_rejecting_requires_a_real_reason(): void
    {
        $request = $this->pendingRequest();

        foreach ([[], ['reason' => ''], ['reason' => 'ab']] as $body) {
            $this->asSuper()->postJson(route('admin.case-assignment-requests.reject', $request->id), $body)
                ->assertUnprocessable()->assertJsonValidationErrors('reason');
        }

        $this->assertSame('pending', $request->fresh()->status->value);
    }

    public function test_rejecting_notifies_the_client_with_the_reason_and_leaves_the_case_untouched(): void
    {
        Notification::fake();
        $request = $this->pendingRequest();

        $this->asSuper()->postJson(route('admin.case-assignment-requests.reject', $request->id), ['reason' => 'Specializes in a different area'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $fresh = $request->fresh();
        $this->assertSame('rejected', $fresh->status->value);
        $this->assertSame('Specializes in a different area', $fresh->reason);
        $this->assertSame($this->super->id, $fresh->admin_id);

        $case = $fresh->case->fresh();
        $this->assertNull($case->advocate_id);
        $this->assertSame(CaseStatus::Pending, $case->status);

        Notification::assertSentTo($fresh->client, CaseAssignmentRejected::class);
    }

    public function test_rejecting_twice_is_rejected_cleanly(): void
    {
        $request = $this->pendingRequest();

        $this->asSuper()->postJson(route('admin.case-assignment-requests.reject', $request->id), ['reason' => 'first'])->assertOk();
        $this->asSuper()->postJson(route('admin.case-assignment-requests.reject', $request->id), ['reason' => 'second'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This request has already been decided.');
    }
}

<?php

namespace App\Services\Cases;

use App\Enums\CaseAssignmentRequestStatus;
use App\Enums\CaseStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\InvalidStateTransition;
use App\Models\CaseAssignmentRequest;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\Admin\AdminAlertDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Lets a client pick a lawyer for their submitted case. This never touches
 * cases.advocate_id directly - it only creates a pending request, which an
 * admin must approve (CaseAssignmentModerationService) before the lawyer is
 * assigned and can see the case at all.
 */
class CaseAssignmentService
{
    public function __construct(private readonly AdminAlertDispatcher $alerts)
    {
    }

    public function requestAssignment(LegalCase $case, User $lawyer): CaseAssignmentRequest
    {
        return DB::transaction(function () use ($case, $lawyer) {
            $locked = LegalCase::whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isDraft()) {
                throw new InvalidStateTransition('Submit the case before assigning a lawyer.');
            }

            if ($locked->status !== CaseStatus::Pending || $locked->advocate_id !== null) {
                throw new InvalidStateTransition('This case already has a lawyer assigned.');
            }

            if ($locked->assignmentRequests()->pending()->exists()) {
                throw new InvalidStateTransition('A request for this case is already awaiting admin review.');
            }

            if (! $lawyer->isLawyer() || $lawyer->lawyerProfile?->verification_status !== VerificationStatus::Verified) {
                throw new InvalidStateTransition('Only verified lawyers can be assigned.');
            }

            if (! $lawyer->practiceAreas()->where('practice_areas.id', $locked->practice_area_id)->exists()) {
                throw new InvalidStateTransition("This lawyer does not handle the case's practice area.");
            }

            $request = CaseAssignmentRequest::create([
                'case_id' => $locked->id,
                'lawyer_id' => $lawyer->user_id,
                'requested_by' => $locked->client_id,
                'status' => CaseAssignmentRequestStatus::Pending,
            ]);

            $this->alerts->caseAssignmentRequested($request);

            return $request;
        });
    }
}

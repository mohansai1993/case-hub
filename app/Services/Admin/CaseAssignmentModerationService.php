<?php

namespace App\Services\Admin;

use App\Enums\CaseAssignmentRequestStatus;
use App\Enums\CaseStatus;
use App\Exceptions\InvalidStateTransition;
use App\Models\Admin;
use App\Models\CaseAssignmentRequest;
use App\Models\LegalCase;
use App\Notifications\CaseAssignmentApproved;
use App\Notifications\CaseAssignmentRejected;
use App\Notifications\LawyerCaseAssigned;
use Illuminate\Support\Facades\DB;

/**
 * Admin decisions on a client's lawyer-assignment request. Mirrors
 * AccountModerationService: re-read under a lock, validate against the
 * *current* state, write the change in one transaction, notify, then alert.
 */
class CaseAssignmentModerationService
{
    public function __construct(private readonly AdminAlertDispatcher $alerts)
    {
    }

    public function approve(CaseAssignmentRequest $request, Admin $by): CaseAssignmentRequest
    {
        $result = DB::transaction(function () use ($request, $by) {
            $locked = CaseAssignmentRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new InvalidStateTransition('This request has already been decided.');
            }

            $case = LegalCase::whereKey($locked->case_id)->lockForUpdate()->firstOrFail();

            if ($case->advocate_id !== null || $case->status !== CaseStatus::Pending) {
                throw new InvalidStateTransition('This case already has a lawyer assigned.');
            }

            $locked->forceFill([
                'status' => CaseAssignmentRequestStatus::Approved,
                'admin_id' => $by->getKey(),
                'decided_at' => now(),
            ])->save();

            $case->update(['advocate_id' => $locked->lawyer_id, 'status' => CaseStatus::Assigned]);

            $locked->load('lawyer', 'client');
            $locked->lawyer->notify(new LawyerCaseAssigned($case));
            $locked->client->notify(new CaseAssignmentApproved($locked));

            return $locked;
        });

        $this->alerts->staffActionTaken(
            $by,
            "approved a lawyer assignment on case #{$result->case_id}.",
            route('admin.case-assignment-request-details', $result->id)
        );

        return $result;
    }

    public function reject(CaseAssignmentRequest $request, Admin $by, string $reason): CaseAssignmentRequest
    {
        $result = DB::transaction(function () use ($request, $reason, $by) {
            $locked = CaseAssignmentRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new InvalidStateTransition('This request has already been decided.');
            }

            $locked->forceFill([
                'status' => CaseAssignmentRequestStatus::Rejected,
                'admin_id' => $by->getKey(),
                'reason' => trim($reason),
                'decided_at' => now(),
            ])->save();

            $locked->load('client');
            $locked->client->notify(new CaseAssignmentRejected($locked));

            return $locked;
        });

        $this->alerts->staffActionTaken(
            $by,
            "rejected a lawyer assignment on case #{$result->case_id}.",
            route('admin.case-assignment-request-details', $result->id)
        );

        return $result;
    }
}

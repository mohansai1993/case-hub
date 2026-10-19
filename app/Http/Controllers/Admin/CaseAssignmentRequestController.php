<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CaseAssignmentRequestStatus;
use App\Exceptions\InvalidStateTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReasonRequest;
use App\Models\CaseAssignmentRequest;
use App\Services\Admin\CaseAssignmentModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaseAssignmentRequestController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(private readonly CaseAssignmentModerationService $moderation)
    {
    }

    public function index(Request $request): View
    {
        $status = CaseAssignmentRequestStatus::tryFrom((string) $request->query('status', 'pending'));

        $requests = CaseAssignmentRequest::with('case.client', 'case.practiceArea', 'lawyer')
            ->when($status, fn ($query, $status) => $query->where('status', $status->value))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.case-assignment-requests', [
            'requests' => $requests,
            'total' => CaseAssignmentRequest::count(),
            'filters' => ['status' => $request->query('status', 'pending')],
        ]);
    }

    public function show(CaseAssignmentRequest $caseAssignmentRequest): View
    {
        return view('admin.case-assignment-request-details', [
            'assignmentRequest' => $caseAssignmentRequest->load('case.client', 'case.practiceArea', 'lawyer.lawyerProfile', 'admin'),
        ]);
    }

    public function approve(Request $request, CaseAssignmentRequest $caseAssignmentRequest): JsonResponse
    {
        return $this->run(fn () => $this->moderation->approve($caseAssignmentRequest, $request->user('admin')), 'Assignment approved.');
    }

    public function reject(ReasonRequest $request, CaseAssignmentRequest $caseAssignmentRequest): JsonResponse
    {
        return $this->run(fn () => $this->moderation->reject($caseAssignmentRequest, $request->user('admin'), $request->reason()), 'Assignment rejected.');
    }

    private function run(callable $action, string $successMessage): JsonResponse
    {
        try {
            $result = $action();
        } catch (InvalidStateTransition $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $successMessage, 'data' => ['status' => $result->status->value]]);
    }
}

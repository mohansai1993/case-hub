<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidStateTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cases\AssignLawyerRequest;
use App\Http\Resources\CaseAssignmentRequestResource;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\Cases\CaseAssignmentService;
use Illuminate\Http\JsonResponse;

class CaseAssignmentController extends Controller
{
    public function __construct(private readonly CaseAssignmentService $assignments)
    {
    }

    /** The client picks a lawyer; this submits the pick for admin review - it does not assign the lawyer yet. */
    public function store(AssignLawyerRequest $request, LegalCase $case): JsonResponse
    {
        abort_unless($case->client_id === $request->user('sanctum')->user_id, 403);

        $lawyer = User::lawyers()->findOrFail($request->input('lawyer_id'));

        try {
            $assignmentRequest = $this->assignments->requestAssignment($case, $lawyer);
        } catch (InvalidStateTransition $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Lawyer assignment submitted for admin review.',
            'data' => new CaseAssignmentRequestResource($assignmentRequest->load('lawyer.lawyerProfile')),
        ], 201);
    }
}

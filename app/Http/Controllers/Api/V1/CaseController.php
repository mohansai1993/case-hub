<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cases\StoreCaseRequest;
use App\Http\Resources\CaseResource;
use App\Models\LegalCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    private const PER_PAGE = 20;

    /** Cases the signed-in user is part of, as either the client or the advocate. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $cases = LegalCase::forParticipant($user)
            ->with('client', 'advocate')
            ->withCount(['messages as unread_count' => fn ($query) => $query
                ->unread()
                ->where('sender_id', '!=', $user->user_id)])
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return response()->json(['data' => CaseResource::collection($cases)]);
    }

    /** Only a client opens a case, by picking the advocate they want to talk to. */
    public function store(StoreCaseRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user->isClient()) {
            return response()->json(['message' => 'Only clients can open a case.'], 403);
        }

        $case = LegalCase::create([
            'client_id' => $user->user_id,
            'advocate_id' => $request->input('advocate_id'),
            'title' => trim($request->string('title')->toString()),
            'status' => CaseStatus::Pending,
        ]);

        return response()->json([
            'message' => 'Case created.',
            'data' => new CaseResource($case->load('client', 'advocate')),
        ], 201);
    }

    public function show(Request $request, LegalCase $case): JsonResponse
    {
        $this->authorizeParticipant($request, $case);
        $user = $request->user('sanctum');

        $case->load('client', 'advocate')->loadCount(['messages as unread_count' => fn ($query) => $query
            ->unread()
            ->where('sender_id', '!=', $user->user_id)]);

        return response()->json(['data' => new CaseResource($case)]);
    }

    /** The advocate agrees to take the case; this is what opens the chat. */
    public function accept(Request $request, LegalCase $case): JsonResponse
    {
        $this->authorizeAdvocate($request, $case);

        if ($case->status !== CaseStatus::Pending) {
            return response()->json(['message' => 'This case has already been decided.'], 422);
        }

        $case->update(['status' => CaseStatus::Accepted]);

        return response()->json(['message' => 'Case accepted.', 'data' => ['status' => $case->status->value]]);
    }

    public function reject(Request $request, LegalCase $case): JsonResponse
    {
        $this->authorizeAdvocate($request, $case);

        if ($case->status !== CaseStatus::Pending) {
            return response()->json(['message' => 'This case has already been decided.'], 422);
        }

        $case->update(['status' => CaseStatus::Rejected]);

        return response()->json(['message' => 'Case rejected.', 'data' => ['status' => $case->status->value]]);
    }

    private function authorizeParticipant(Request $request, LegalCase $case): void
    {
        abort_unless($case->isParticipant($request->user('sanctum')), 403);
    }

    private function authorizeAdvocate(Request $request, LegalCase $case): void
    {
        abort_unless($case->advocate_id === $request->user('sanctum')->user_id, 403);
    }
}

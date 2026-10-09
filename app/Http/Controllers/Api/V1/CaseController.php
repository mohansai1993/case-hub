<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cases\StartCaseDraftRequest;
use App\Http\Requests\Api\Cases\SubmitCaseRequest;
use App\Http\Resources\CaseResource;
use App\Models\LegalCase;
use App\Services\Billing\StorageQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly StorageQuotaService $quota)
    {
    }

    /** Cases the signed-in user is part of, as either the client or the advocate. Drafts never show here. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $cases = LegalCase::forParticipant($user)->submitted()
            ->with('client', 'advocate', 'practiceArea')
            ->withCount(['messages as unread_count' => fn ($query) => $query
                ->unread()
                ->where('sender_id', '!=', $user->user_id)])
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return response()->json(['data' => CaseResource::collection($cases)]);
    }

    /**
     * Starts a draft case so the "Create Case" screen has a case_id to attach
     * evidence to as soon as the client picks their first file - well before
     * the rest of the form (or "Create Case" itself) is filled in or tapped.
     * Every field is optional here; see submit() for the real validation.
     */
    public function store(StartCaseDraftRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user->isClient()) {
            return response()->json(['message' => 'Only clients can open a case.'], 403);
        }

        if (! $this->quota->canCreateNewContent($user)) {
            return response()->json([
                'message' => 'You need an active storage plan with available space to submit a case with evidence.',
                'code' => 'storage_full',
            ], 422);
        }

        $case = LegalCase::create([
            'client_id' => $user->user_id,
            'status' => CaseStatus::Pending,
            'title' => $request->filled('title') ? trim($request->string('title')->toString()) : null,
            'practice_area_id' => $request->input('practice_area_id'),
            'description' => $request->filled('description') ? trim($request->string('description')->toString()) : null,
            'incident_date' => $request->input('incident_date'),
            'location' => $request->filled('location') ? trim($request->string('location')->toString()) : null,
        ]);

        return response()->json([
            'message' => 'Draft case started.',
            'data' => new CaseResource($case->load('client', 'practiceArea')),
        ], 201);
    }

    /**
     * Finalizes a draft - this is what the "Create Case" button calls. The
     * case becomes visible in the client's list and to admins for review;
     * evidence already uploaded against the draft stays attached as-is.
     */
    public function submit(SubmitCaseRequest $request, LegalCase $case): JsonResponse
    {
        $this->authorizeOwner($request, $case);

        if (! $case->isDraft()) {
            return response()->json(['message' => 'This case has already been submitted.'], 422);
        }

        $case->update([
            'title' => trim($request->string('title')->toString()),
            'practice_area_id' => $request->input('practice_area_id'),
            'description' => trim($request->string('description')->toString()),
            'incident_date' => $request->input('incident_date'),
            'location' => trim($request->string('location')->toString()),
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Case submitted for review.',
            'data' => new CaseResource($case->fresh()->load('client', 'practiceArea')),
        ]);
    }

    /** Lets the client back out of a draft they never finished - deletes it and anything they'd already uploaded. */
    public function destroy(Request $request, LegalCase $case): JsonResponse
    {
        $this->authorizeOwner($request, $case);

        if (! $case->isDraft()) {
            return response()->json(['message' => 'A submitted case cannot be deleted.'], 422);
        }

        $case->purgeWithDocuments();

        return response()->json(['message' => 'Draft discarded.']);
    }

    public function show(Request $request, LegalCase $case): JsonResponse
    {
        $this->authorizeParticipant($request, $case);
        $user = $request->user('sanctum');

        $case->load('client', 'advocate', 'practiceArea')->loadCount(['messages as unread_count' => fn ($query) => $query
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

    private function authorizeOwner(Request $request, LegalCase $case): void
    {
        abort_unless($case->client_id === $request->user('sanctum')->user_id, 403);
    }

    private function authorizeAdvocate(Request $request, LegalCase $case): void
    {
        abort_unless($case->advocate_id === $request->user('sanctum')->user_id, 403);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CaseStatus;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cases\SendMessageRequest;
use App\Http\Resources\CaseMessageResource;
use App\Models\CaseMessage;
use App\Models\LegalCase;
use App\Notifications\NewCaseMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseMessageController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request, LegalCase $case): JsonResponse
    {
        $this->authorizeParticipant($request, $case);

        $messages = $case->messages()->latest('id')->paginate(self::PER_PAGE);

        return response()->json(['data' => CaseMessageResource::collection($messages)]);
    }

    public function store(SendMessageRequest $request, LegalCase $case): JsonResponse
    {
        $user = $request->user('sanctum');
        $this->authorizeParticipant($request, $case);

        if ($case->status !== CaseStatus::Accepted) {
            return response()->json(['message' => 'This case is not open for messages yet.'], 422);
        }

        $message = CaseMessage::create([
            'case_id' => $case->id,
            'sender_id' => $user->user_id,
            'body' => trim($request->string('body')->toString()),
        ]);
        $message->setRelation('sender', $user);

        broadcast(new MessageSent($message))->toOthers();

        $case->otherParty($user)->notify(new NewCaseMessage($message));

        return response()->json(['data' => new CaseMessageResource($message)], 201);
    }

    /** Marks every message the other party sent as read. */
    public function markRead(Request $request, LegalCase $case): JsonResponse
    {
        $user = $request->user('sanctum');
        $this->authorizeParticipant($request, $case);

        $case->messages()->unread()->where('sender_id', '!=', $user->user_id)->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked as read.']);
    }

    private function authorizeParticipant(Request $request, LegalCase $case): void
    {
        abort_unless($case->isParticipant($request->user('sanctum')), 403);
    }
}

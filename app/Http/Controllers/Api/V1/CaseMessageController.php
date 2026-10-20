<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CaseStatus;
use App\Events\MessageSent;
use App\Events\MessagesDelivered;
use App\Events\MessagesRead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cases\SendMessageRequest;
use App\Http\Resources\CaseMessageResource;
use App\Models\CaseMessage;
use App\Models\LegalCase;
use App\Notifications\NewCaseMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CaseMessageController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request, LegalCase $case): JsonResponse
    {
        $this->authorizeParticipant($request, $case);

        // Fetch the most recent page (so page 1 is always "the latest conversation"),
        // then flip it to oldest-first within that page - frontend renders top-to-bottom
        // and wants the newest message at the bottom, not the top.
        $messages = $case->messages()->latest('id')->paginate(self::PER_PAGE);
        $messages->setCollection($messages->getCollection()->reverse()->values());

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

        Log::info("[chat:broadcast] message.sent dispatched on channel case.{$case->id} (message #{$message->id}, sender {$user->user_id})");

        // Delayed so an already-connected recipient's delivered-ack (POST
        // .../messages/delivered) has time to land first - see NewCaseMessage::via().
        $case->otherParty($user)->notify(
            (new NewCaseMessage($message))->delay(now()->addSeconds(15))
        );

        return response()->json(['data' => new CaseMessageResource($message)], 201);
    }

    /**
     * The device ack: "I'm connected/online and have everything the other
     * side sent so far" - grey tick -> double grey tick. Independent of the
     * chat screen being open; call this as soon as the socket connects.
     */
    public function delivered(Request $request, LegalCase $case): JsonResponse
    {
        $user = $request->user('sanctum');
        $this->authorizeParticipant($request, $case);

        // Logged unconditionally (even when nothing was pending) so this line in
        // storage/logs/laravel.log is proof the client actually called this endpoint -
        // the single most common chat-tick bug is the frontend never calling it.
        Log::info("[chat:ack] delivered-ack received from user {$user->user_id} for case {$case->id}");

        $now = now();
        $updated = $case->messages()->undelivered()->where('sender_id', '!=', $user->user_id)
            ->update(['delivered_at' => $now]);

        if ($updated > 0) {
            broadcast(new MessagesDelivered($case->id, $user->user_id, $now->toIso8601String()));
            Log::info("[chat:broadcast] messages.delivered dispatched on channel case.{$case->id} ({$updated} message(s))");
        }

        return response()->json(['message' => 'Marked as delivered.']);
    }

    /** Marks every message the other party sent as read - double grey tick -> double blue tick. */
    public function markRead(Request $request, LegalCase $case): JsonResponse
    {
        $user = $request->user('sanctum');
        $this->authorizeParticipant($request, $case);

        Log::info("[chat:ack] read-ack received from user {$user->user_id} for case {$case->id}");

        $now = now();
        // Reading implies delivered, in case the delivered-ack was somehow missed.
        $updated = $case->messages()->unread()->where('sender_id', '!=', $user->user_id)
            ->update(['read_at' => $now, 'delivered_at' => $now]);

        if ($updated > 0) {
            broadcast(new MessagesRead($case->id, $user->user_id, $now->toIso8601String()));
            Log::info("[chat:broadcast] messages.read dispatched on channel case.{$case->id} ({$updated} message(s))");
        }

        return response()->json(['message' => 'Marked as read.']);
    }

    private function authorizeParticipant(Request $request, LegalCase $case): void
    {
        abort_unless($case->isParticipant($request->user('sanctum')), 403);
    }
}

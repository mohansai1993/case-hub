<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Admin\NoRecipientsFound;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecipientSearchRequest;
use App\Http\Requests\Admin\SendNotificationRequest;
use App\Http\Requests\Admin\StoreNotificationDraftRequest;
use App\Models\NotificationBroadcast;
use App\Models\NotificationDraft;
use App\Models\User;
use App\Services\Admin\NotificationBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class NotificationController extends Controller
{
    private const RECIPIENT_LIMIT = 50;

    public function __construct(private readonly NotificationBroadcastService $broadcasts)
    {
    }

    public function index(): View
    {
        $drafts = NotificationDraft::latest('id')->limit(20)->get()->map(fn (NotificationDraft $d) => [
            'id' => $d->id,
            'title' => $d->title,
            'message' => $d->message,
            'created' => $d->created_at?->format('d M Y'),
        ])->values();

        $sent = NotificationBroadcast::latest('id')->limit(20)->get()->map(fn (NotificationBroadcast $s) => [
            'title' => $s->title,
            'to' => $s->recipientsLabel(),
            'type' => $s->audienceLabel(),
            'date' => $s->created_at?->format('d M Y, H:i'),
        ])->values();

        return view('admin.notifications', [
            'draftsForJs' => $drafts,
            'sentForJs' => $sent,
        ]);
    }

    public function recipients(RecipientSearchRequest $request): JsonResponse
    {
        $users = User::where('type', $request->type()->value)
            ->when($request->search(), fn ($query, $term) => $this->search($query, $term))
            ->orderBy('name')
            ->limit(self::RECIPIENT_LIMIT)
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'id' => $user->user_id,
                'reference' => $user->reference(),
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status->value,
            ]),
        ]);
    }

    public function storeDraft(StoreNotificationDraftRequest $request): JsonResponse
    {
        $draft = $this->broadcasts->createDraft($request->user('admin'), $request->string('title')->toString(), $request->string('message')->toString());

        return response()->json([
            'message' => 'Notification draft created.',
            'data' => $draft,
        ], 201);
    }

    public function destroyDraft(NotificationDraft $draft): Response
    {
        $draft->delete();

        return response()->noContent();
    }

    public function send(SendNotificationRequest $request): JsonResponse
    {
        $draft = NotificationDraft::findOrFail($request->input('draft_id'));

        try {
            $broadcast = $this->broadcasts->send(
                $request->user('admin'),
                $draft,
                $request->audience(),
                $request->boolean('bulk'),
                $request->userIds(),
            );
        } catch (NoRecipientsFound $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Notification sent to ' . $broadcast->recipient_count . ' recipient(s).',
            'data' => [
                'title' => $broadcast->title,
                'to' => $broadcast->recipientsLabel(),
                'type' => $broadcast->audienceLabel(),
                'date' => $broadcast->created_at->toIso8601String(),
            ],
        ]);
    }

    private function search($query, string $term)
    {
        $like = '%' . addcslashes($term, '%_\\') . '%';

        return $query->where(fn ($q) => $q
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like));
    }
}

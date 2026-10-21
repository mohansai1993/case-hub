<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The topbar bell icon's data: polled on an interval so it "feels" real-time
 * without needing a browser-side WebSocket client the admin panel doesn't
 * otherwise have (see docs/02-admin-panel.md).
 */
class AlertController extends Controller
{
    private const RECENT_LIMIT = 10;

    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        return response()->json([
            'unread_count' => $admin->unreadNotifications()->count(),
            'notifications' => $admin->notifications()
                ->latest()
                ->limit(self::RECENT_LIMIT)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? '',
                    'body' => $notification->data['body'] ?? '',
                    'action_url' => $notification->data['action_url'] ?? null,
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at->diffForHumans(),
                ]),
        ]);
    }

    /**
     * The bell icon's "See all notifications" link: every alert the admin has
     * ever received, paginated, instead of the dropdown's last-10 JSON feed.
     */
    public function all(Request $request): View
    {
        $admin = $request->user('admin');

        return view('admin.alerts', [
            'notifications' => $admin->notifications()->latest()->paginate(20),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user('admin')->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user('admin')->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}

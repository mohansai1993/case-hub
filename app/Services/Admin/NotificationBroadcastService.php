<?php

namespace App\Services\Admin;

use App\Enums\UserType;
use App\Exceptions\Admin\NoRecipientsFound;
use App\Models\Admin;
use App\Models\NotificationBroadcast;
use App\Models\NotificationDraft;
use App\Models\User;
use App\Notifications\GenericPushNotification;
use Illuminate\Support\Facades\Notification;

class NotificationBroadcastService
{
    public function createDraft(Admin $by, string $title, string $message): NotificationDraft
    {
        return NotificationDraft::create([
            'title' => trim($title),
            'message' => trim($message),
            'admin_id' => $by->getKey(),
        ]);
    }

    /**
     * Fan the draft out to every selected recipient (in-app + push, queued)
     * and log one audit row for the "Sent Notifications" list.
     *
     * @param  string[]  $userIds  ignored when $bulk is true
     */
    public function send(Admin $by, NotificationDraft $draft, UserType $audience, bool $bulk, array $userIds): NotificationBroadcast
    {
        $recipients = User::where('type', $audience->value)
            ->when(! $bulk, fn ($query) => $query->whereIn('user_id', $userIds))
            ->get();

        if ($recipients->isEmpty()) {
            throw new NoRecipientsFound('No matching recipients were found.');
        }

        Notification::send(
            $recipients,
            new GenericPushNotification($draft->title, $draft->message, ['draft_id' => $draft->id]),
        );

        return NotificationBroadcast::create([
            'notification_draft_id' => $draft->id,
            'title' => $draft->title,
            'message' => $draft->message,
            'audience' => $audience->value,
            'is_bulk' => $bulk,
            'recipient_count' => $recipients->count(),
            'recipient_names' => $bulk ? null : $recipients->pluck('name')->implode(', '),
            'admin_id' => $by->getKey(),
        ]);
    }
}

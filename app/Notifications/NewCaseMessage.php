<?php

namespace App\Notifications;

use App\Models\CaseMessage;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The push/in-app fallback for when the recipient isn't actively connected
 * to the Reverb channel (app backgrounded, etc). Queued: unlike the OTP
 * codes, nobody is staring at a screen waiting for this specific delivery.
 *
 * Dispatched with a short delay (see CaseMessageController::store()) so that,
 * by the time this runs, an already-connected recipient's client has had a
 * chance to call POST .../messages/delivered and set delivered_at - in which
 * case they've already seen this message live over the socket and the FCM
 * push is skipped (database notification still records it either way).
 */
class NewCaseMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CaseMessage $message)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->message->fresh()?->delivered_at === null) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->message->sender->name,
            'body' => Str::limit($this->message->body, 120),
            'data' => ['case_id' => $this->message->case_id, 'message_id' => $this->message->id],
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->message->sender->name,
            'body' => Str::limit($this->message->body, 120),
            'data' => ['case_id' => $this->message->case_id, 'message_id' => $this->message->id],
        ];
    }
}

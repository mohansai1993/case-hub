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
 */
class NewCaseMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CaseMessage $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
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

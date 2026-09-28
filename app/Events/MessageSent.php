<?php

namespace App\Events;

use App\Models\CaseMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast immediately (not queued): the whole point is that both sides see
 * the message the instant it's sent, same as VerificationCode/PasswordResetOtp
 * are sent synchronously rather than queued.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly CaseMessage $message)
    {
    }

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('case.' . $this->message->case_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'case_id' => $this->message->case_id,
            'sender_id' => $this->message->sender_id,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}

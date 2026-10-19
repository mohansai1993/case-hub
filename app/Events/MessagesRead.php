<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a participant opens the chat and marks the other side's
 * messages read (double grey tick -> double blue tick) - see
 * CaseMessageController::markRead().
 */
class MessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $caseId,
        public readonly string $readBy,
        public readonly string $readAt,
    ) {
    }

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('case.' . $this->caseId)];
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'read_by' => $this->readBy,
            'read_at' => $this->readAt,
        ];
    }
}

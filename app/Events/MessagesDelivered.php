<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a participant's device acknowledges it has the messages the
 * other side sent (grey tick -> double grey tick) - see
 * CaseMessageController::delivered(). Broadcast immediately, same reasoning
 * as MessageSent: the tick update is only useful if it's instant.
 */
class MessagesDelivered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $caseId,
        public readonly string $deliveredBy,
        public readonly string $deliveredAt,
    ) {
    }

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('case.' . $this->caseId)];
    }

    public function broadcastAs(): string
    {
        return 'messages.delivered';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'delivered_by' => $this->deliveredBy,
            'delivered_at' => $this->deliveredAt,
        ];
    }
}

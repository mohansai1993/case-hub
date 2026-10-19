<?php

namespace App\Notifications;

use App\Models\CaseAssignmentRequest;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** An admin rejected the lawyer the client picked; the client may pick another one. */
class CaseAssignmentRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CaseAssignmentRequest $request)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Lawyer assignment rejected',
            'body' => Str::limit((string) $this->request->reason, 120),
            'data' => ['case_id' => $this->request->case_id],
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Lawyer assignment rejected',
            'body' => Str::limit((string) $this->request->reason, 120),
            'data' => ['case_id' => $this->request->case_id],
        ];
    }
}

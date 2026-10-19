<?php

namespace App\Notifications;

use App\Models\CaseAssignmentRequest;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** An admin approved the lawyer the client picked; now waiting on the lawyer's own accept/reject. */
class CaseAssignmentApproved extends Notification implements ShouldQueue
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
        $this->request->loadMissing('lawyer');

        return [
            'title' => 'Lawyer assignment approved',
            'body' => "{$this->request->lawyer->name} will review your case.",
            'data' => ['case_id' => $this->request->case_id],
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $this->request->loadMissing('lawyer');

        return [
            'title' => 'Lawyer assignment approved',
            'body' => "{$this->request->lawyer->name} will review your case.",
            'data' => ['case_id' => $this->request->case_id],
        ];
    }
}

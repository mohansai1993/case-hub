<?php

namespace App\Notifications;

use App\Models\LegalCase;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** An admin approved the client's pick: this lawyer now has the case in their "New" queue. */
class LawyerCaseAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly LegalCase $case)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New case assigned to you',
            'body' => $this->case->title,
            'data' => ['case_id' => $this->case->id],
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'New case assigned to you',
            'body' => $this->case->title,
            'data' => ['case_id' => $this->case->id],
        ];
    }
}

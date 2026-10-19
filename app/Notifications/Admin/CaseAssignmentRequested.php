<?php

namespace App\Notifications\Admin;

use App\Models\CaseAssignmentRequest;
use Illuminate\Notifications\Notification;

/**
 * Powers the admin topbar's bell icon. Delivered synchronously on purpose
 * (no ShouldQueue): the bell is polled right after this fires, so a queued
 * delivery sitting behind a worker that isn't running would make the alert
 * silently never show up.
 */
class CaseAssignmentRequested extends Notification
{
    public function __construct(private readonly CaseAssignmentRequest $request)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->request->loadMissing('case', 'lawyer');

        return [
            'title' => 'New lawyer assignment request',
            'body' => "{$this->request->case->caseNumber()} requested {$this->request->lawyer->name}",
            'action_url' => route('admin.case-assignment-request-details', $this->request->id),
        ];
    }
}

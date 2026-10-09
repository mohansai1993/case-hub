<?php

namespace App\Notifications\Admin;

use App\Models\Admin;
use Illuminate\Notifications\Notification;

/**
 * Oversight alert to Super Admins whenever a (non-super-admin) staff member
 * takes a moderation action - approve/reject/suspend/activate. Delivered
 * synchronously, same reasoning as NewAccountRegistered.
 */
class StaffActionTaken extends Notification
{
    public function __construct(
        private readonly Admin $staff,
        private readonly string $summary,
        private readonly ?string $actionUrl = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Staff activity',
            'body' => "{$this->staff->name}: {$this->summary}",
            'action_url' => $this->actionUrl,
        ];
    }
}

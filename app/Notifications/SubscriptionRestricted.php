<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Fired by the daily grace-period sweep once the 7-day window has passed. */
class SubscriptionRestricted extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Account restricted',
            'body' => 'Your grace period has ended. Your data is at risk of deletion. Subscribe to a plan to restore access.',
        ];
    }
}

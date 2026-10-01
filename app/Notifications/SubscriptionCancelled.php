<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Fired the moment a client cancels: tells them the 7-day download window has started. */
class SubscriptionCancelled extends Notification
{
    public function __construct(private readonly Carbon $graceEndsAt)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Subscription cancelled',
            'body' => "Download your data before {$this->graceEndsAt->format('d M Y')} - after that your account is restricted and your data is at risk of deletion.",
        ];
    }
}

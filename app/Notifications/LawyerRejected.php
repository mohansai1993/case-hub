<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when an admin rejects a lawyer's verification request. Verification
 * never blocks sign-in, so the lawyer can still see this in-app - it just
 * means their profile won't show the "Verified" badge to clients.
 */
class LawyerRejected extends Notification
{
    public function __construct(private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Advocate application rejected',
            'body' => 'Reason: ' . $this->reason,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your CaseHub advocate application')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('After reviewing your advocate profile, we are unable to approve your account at this time.')
            ->line('Reason: ' . $this->reason)
            ->line('If you believe this is a mistake, please contact support.');
    }
}

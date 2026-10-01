<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when an admin rejects a lawyer's verification request. Mail only -
 * a rejected lawyer can never sign in to see an in-app notification, so a
 * database record here would just be dead data nobody can read.
 */
class LawyerRejected extends Notification
{
    public function __construct(private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
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

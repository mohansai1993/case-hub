<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when an admin approves a lawyer's verification request. Sent by
 * mail too (not just database) - the lawyer cannot sign in to see the
 * in-app bell until this approval happens, so email is how they actually
 * learn they can now log in.
 */
class LawyerApproved extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Account approved',
            'body' => 'Your advocate profile has been verified. You can now sign in and start accepting cases.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your CaseHub advocate account has been approved')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your advocate profile has been reviewed and verified by our team.')
            ->line('You can now sign in to CaseHub and start accepting cases.');
    }
}

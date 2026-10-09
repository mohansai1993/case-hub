<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when an admin approves a lawyer's verification request. Sent by
 * mail too (not just database) - the lawyer might not have the app open
 * right when this happens, so email is how they're actually likely to
 * notice. Verification never blocked sign-in; approval just unlocks the
 * "Verified" badge clients see on their profile.
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
            'body' => 'Your advocate profile has been verified - clients will now see a "Verified" badge on your profile.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your CaseHub advocate account has been approved')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your advocate profile has been reviewed and verified by our team.')
            ->line('Clients will now see a "Verified" badge on your profile.');
    }
}

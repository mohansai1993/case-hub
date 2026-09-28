<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The code behind the app's "Verify Your Account" / forgot-password screens.
 * Sent synchronously: the user is waiting on the OTP screen.
 */
class VerificationCode extends Notification
{
    /** @param  'email'|'sms'  $via */
    public function __construct(
        private readonly string $code,
        private readonly string $via = 'sms',
    ) {
    }

    public function via(object $notifiable): array
    {
        return [$this->via === 'email' ? 'mail' : SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your CaseHub verification code')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Use this code to verify your CaseHub account:')
            ->line('**' . $this->code . '**')
            ->line('It is valid for ' . config('otp.app.ttl') . ' minutes. Never share it with anyone.');
    }

    public function toSms(object $notifiable): string
    {
        return 'CaseHub: ' . $this->code . ' is your verification code. Valid for '
            . config('otp.app.ttl') . ' minutes. Do not share it with anyone.';
    }
}

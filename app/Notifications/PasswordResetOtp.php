<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivered synchronously on purpose (no ShouldQueue): the user is staring at
 * the OTP screen, and a queue with no running worker would silently stall it.
 */
class PasswordResetOtp extends Notification
{
    /** @param  'email'|'mobile'  $via */
    public function __construct(
        private readonly string $code,
        private readonly string $via,
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
            ->line('Use this code to reset your CaseHub admin password:')
            ->line('**' . $this->code . '**')
            ->line('It is valid for ' . config('otp.ttl') . ' minutes. Never share it with anyone.')
            ->line('If you did not request this, you can safely ignore this email.');
    }

    public function toSms(object $notifiable): string
    {
        return 'CaseHub: ' . $this->code . ' is your verification code. Valid for '
            . config('otp.ttl') . ' minutes. Do not share it with anyone.';
    }
}

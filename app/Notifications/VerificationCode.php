<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Notification;

/**
 * The SMS behind the app's "Verify Your Account" / forgot-password screens.
 * Sent synchronously: the user is waiting on the OTP screen.
 */
class VerificationCode extends Notification
{
    public function __construct(private readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toSms(object $notifiable): string
    {
        return 'CaseHub: ' . $this->code . ' is your verification code. Valid for '
            . config('otp.app.ttl') . ' minutes. Do not share it with anyone.';
    }
}

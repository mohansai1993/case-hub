<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsGateway;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $mobile = $notifiable->routeNotificationFor('sms', $notification);

        if (! $mobile) {
            return;
        }

        $this->gateway->send($mobile, $notification->toSms($notifiable));
    }
}

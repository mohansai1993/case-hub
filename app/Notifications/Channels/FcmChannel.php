<?php

namespace App\Notifications\Channels;

use App\Contracts\PushGateway;
use App\Models\DeviceToken;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(private readonly PushGateway $gateway)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $tokens = $notifiable->routeNotificationFor('fcm', $notification);

        if (empty($tokens)) {
            return;
        }

        $payload = $notification->toFcm($notifiable);

        $invalid = $this->gateway->send($tokens, $payload['title'], $payload['body'], $payload['data'] ?? []);

        if ($invalid !== []) {
            DeviceToken::whereIn('token', $invalid)->delete();
        }
    }
}

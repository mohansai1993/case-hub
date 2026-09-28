<?php

namespace App\Services\Push;

use App\Contracts\PushGateway;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class FirebasePushGateway implements PushGateway
{
    private readonly Messaging $messaging;

    public function __construct()
    {
        $this->messaging = (new Factory)
            ->withServiceAccount(config('firebase.credentials'))
            ->createMessaging();
    }

    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        if ($tokens === []) {
            return [];
        }

        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($title, $body))
            ->withData(array_map('strval', $data));

        $report = $this->messaging->sendMulticast($message, $tokens);

        // Malformed tokens (invalid) and tokens no longer registered with
        // Firebase (unknown - app uninstalled, token expired) both mean the
        // row in device_tokens is stale and should be dropped.
        return [...$report->invalidTokens(), ...$report->unknownTokens()];
    }
}

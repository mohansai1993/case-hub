<?php

namespace App\Services\Push;

use App\Contracts\PushGateway;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Development stand-in: writes the notification to the log instead of
 * calling Firebase.
 *
 * It refuses to run in production so an unconfigured push driver fails
 * loudly rather than silently "sending" notifications into a log file.
 */
class LogPushGateway implements PushGateway
{
    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('No push provider is configured. Set PUSH_DRIVER=firebase.');
        }

        Log::info('[push:log] to ' . implode(', ', $tokens) . ": {$title} - {$body}", $data);

        return [];
    }
}

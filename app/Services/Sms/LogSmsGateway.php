<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Development stand-in: writes the message to the log instead of sending it.
 *
 * It refuses to run in production so an unconfigured SMS provider fails
 * loudly rather than silently "sending" one-time codes into a log file.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $mobile, string $message): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('No SMS provider is configured. Bind an App\Contracts\SmsGateway implementation.');
        }

        Log::info('[sms:log] to ' . $mobile . ': ' . $message);
    }
}

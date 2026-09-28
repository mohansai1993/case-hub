<?php

namespace App\Contracts;

interface PushGateway
{
    /**
     * Deliver a push notification to every given token in one call.
     *
     * @param  string[]  $tokens
     * @param  array<string, mixed>  $data
     * @return string[] tokens FCM reported as invalid/unregistered - the caller
     *                   should delete them from device_tokens.
     */
    public function send(array $tokens, string $title, string $body, array $data = []): array;
}

<?php

namespace App\Contracts;

interface SmsGateway
{
    /**
     * Deliver a text message. Implementations must throw on failure so the
     * caller never reports a message as sent when it was not.
     *
     * @param  string  $mobile  10-digit mobile number (see App\Support\Identifier)
     */
    public function send(string $mobile, string $message): void;
}

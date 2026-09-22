<?php

namespace App\Services\Sms;

interface SmsGateway
{
    /** Whether what this gateway needs (credentials...) is filled in. */
    public function isConfigured(): bool;

    /**
     * Send one message. Never throws: a gateway that is down or refuses gives a failed result.
     *
     * @param  string  $to  digits only, with the country code
     */
    public function send(string $to, string $message): SmsResult;
}

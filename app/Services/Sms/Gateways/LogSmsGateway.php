<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Log;

/**
 * Writes the message to the application log instead of sending it. For development; the log
 * then holds one-time codes, so the settings page only offers it outside production.
 */
class LogSmsGateway implements SmsGateway
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $to, string $message): SmsResult
    {
        Log::info('SMS (log driver)', ['to' => $to, 'message' => $message]);

        return new SmsResult(true, 'log');
    }
}

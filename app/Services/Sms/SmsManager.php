<?php

namespace App\Services\Sms;

use App\Models\NotificationLog;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Sms\Gateways\LogSmsGateway;
use App\Services\Sms\Gateways\TamimahSmsGateway;
use Throwable;

/**
 * The one way the app sends an SMS: picks the gateway chosen in the settings, keeps a log of every
 * attempt, and never lets a failing gateway break the caller (a customer whose SMS fails still has
 * their order).
 *
 * Drivers: "tamimah" (real messages), "log" (development: to the application log), "demo" (nothing
 * is sent; the login page shows the code on screen, for presentations).
 */
class SmsManager
{
    public const TAMIMAH = 'tamimah';

    public const LOG = 'log';

    public const DEMO = 'demo';

    public function driver(): ?string
    {
        $driver = (string) SiteSetting::get('sms.driver', '');

        return in_array($driver, [self::TAMIMAH, self::LOG, self::DEMO], true) ? $driver : null;
    }

    public function isDemo(): bool
    {
        return $this->driver() === self::DEMO;
    }

    private function gateway(): ?SmsGateway
    {
        return match ($this->driver()) {
            self::TAMIMAH => app(TamimahSmsGateway::class),
            self::LOG => app(LogSmsGateway::class),
            default => null,
        };
    }

    /** Whether a message can actually reach a phone (the demo driver shows the code instead). */
    public function canSend(): bool
    {
        return $this->isDemo() || ($this->gateway()?->isConfigured() ?? false);
    }

    /**
     * @param  string  $type  what the message is for (otp, order_received, test...), for the log
     * @param  bool  $sensitive  keep the text out of the log (one-time codes)
     */
    public function send(string $to, string $message, string $type, ?ServiceOrder $order = null, bool $sensitive = false): SmsResult
    {
        $gateway = $this->gateway();

        if ($this->isDemo()) {
            $result = new SmsResult(true, 'demo', null, ['note' => 'demo driver: nothing was sent']);
        } elseif (! $gateway || ! $gateway->isConfigured()) {
            $result = SmsResult::failed('No SMS gateway is set up.');
        } else {
            try {
                $result = $gateway->send($to, $message);
            } catch (Throwable $e) {
                $result = SmsResult::failed('SMS gateway error: '.$e->getMessage());
            }
        }

        NotificationLog::create([
            'service_order_id' => $order?->getKey(),
            'customer_id' => $order?->customer_id,
            'channel' => 'sms',
            'type' => $type,
            'recipient' => $to,
            'status' => $result->ok ? 'sent' : 'failed',
            'message' => $sensitive ? null : $message,
            'provider_reference' => $result->reference,
            'error' => $result->error,
            'response' => $result->response ?: null,
        ]);

        return $result;
    }
}

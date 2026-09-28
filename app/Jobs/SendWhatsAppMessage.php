<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One WhatsApp text to one number, off the request. The notifier logs the outcome and never
 * throws, so there is nothing to retry (and a retry could send the same text twice).
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $to,
        public readonly string $message,
        public readonly string $type,
    ) {}

    public function handle(WhatsAppNotifier $notifier): void
    {
        $notifier->send($this->to, $this->message, $this->type);
    }
}

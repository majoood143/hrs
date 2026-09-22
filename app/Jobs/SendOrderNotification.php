<?php

namespace App\Jobs;

use App\Mail\OrderCompletedMail;
use App\Mail\OrderReceivedMail;
use App\Mail\OrderRefundedMail;
use App\Mail\OrderRejectedMail;
use App\Models\NotificationLog;
use App\Models\ServiceOrder;
use App\Services\Notifications\OrderMessages;
use App\Services\Notifications\OrderNotifier;
use App\Services\Sms\SmsManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One message (email or SMS) about one order. It never throws: whatever happens is written to the
 * notification log, and an admin can resend from the order page. That also means a retry can never
 * send the same message twice, and a message already sent for this order is not sent again unless
 * an admin asks for it.
 */
class SendOrderNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    /** One recipient, one message: bounded so a stalled SMTP/SMS connection cannot hang a worker indefinitely. */
    public int $timeout = 30;

    public function __construct(
        public readonly int $orderId,
        public readonly string $channel,
        public readonly string $type,
        public readonly bool $force = false,
    ) {}

    public function handle(SmsManager $sms, OrderMessages $messages): void
    {
        $order = ServiceOrder::query()->with(['service', 'form', 'submission'])->find($this->orderId);

        if (! $order || (! $this->force && $this->alreadySent($order))) {
            return;
        }

        $recipient = $this->channel === 'sms' ? $order->customer_phone : $order->customer_email;

        if (blank($recipient)) {
            $this->log($order, (string) $recipient, 'skipped', error: $this->channel === 'sms' ? 'The order has no phone number.' : 'The order has no email address.');

            return;
        }

        // the customer's own language, then the visitor's back (this may run inside a web request)
        $previous = App::getLocale();
        App::setLocale($order->locale ?: 'en');

        try {
            $this->channel === 'sms'
                ? $sms->send($recipient, $messages->sms($this->type, $order), $this->type, $order)
                : $this->sendMail($order, $recipient);
        } catch (Throwable $e) {
            report($e);
            $this->log($order, $recipient, 'failed', error: $e->getMessage());
        } finally {
            App::setLocale($previous);
        }
    }

    private function sendMail(ServiceOrder $order, string $to): void
    {
        $mailable = match ($this->type) {
            OrderNotifier::COMPLETED => new OrderCompletedMail($order),
            OrderNotifier::REJECTED => new OrderRejectedMail($order),
            OrderNotifier::REFUNDED => new OrderRefundedMail($order),
            default => new OrderReceivedMail($order),
        };

        Mail::to($to)->send($mailable);

        $this->log($order, $to, 'sent', message: $mailable->envelope()->subject);
    }

    private function alreadySent(ServiceOrder $order): bool
    {
        return NotificationLog::query()
            ->where('service_order_id', $order->getKey())
            ->where('channel', $this->channel)
            ->where('type', $this->type)
            ->where('status', 'sent')
            ->exists();
    }

    private function log(ServiceOrder $order, string $recipient, string $status, ?string $message = null, ?string $error = null): void
    {
        NotificationLog::create([
            'service_order_id' => $order->getKey(),
            'customer_id' => $order->customer_id,
            'channel' => $this->channel,
            'type' => $this->type,
            'recipient' => $recipient,
            'status' => $status,
            'message' => $message,
            'error' => $error,
        ]);
    }
}

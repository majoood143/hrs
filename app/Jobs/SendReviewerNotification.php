<?php

namespace App\Jobs;

use App\Mail\ReviewerOrderMail;
use App\Models\NotificationLog;
use App\Models\ServiceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells the addresses a form notifies that a paid order is in. For a form linked to a priced service
 * the plain "new submission" email is held back until payment (an unpaid request is not work yet),
 * and this is the email that replaces it. Never throws, and sends each address once.
 */
class SendReviewerNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public const TYPE = 'reviewer_new_order';

    public int $tries = 1;

    /** Loops over every notification address: bounded so one stalled send cannot hang the worker for the whole batch. */
    public int $timeout = 60;

    public function __construct(public readonly int $orderId, public readonly bool $force = false) {}

    public function handle(): void
    {
        $order = ServiceOrder::query()->with(['service', 'form', 'submission'])->find($this->orderId);

        foreach ($order?->form?->notificationEmails() ?? [] as $address) {
            $alreadySent = ! $this->force && NotificationLog::query()
                ->where('service_order_id', $order->getKey())
                ->where('type', self::TYPE)
                ->where('recipient', $address)
                ->where('status', 'sent')
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $mailable = new ReviewerOrderMail($order);

            try {
                Mail::to($address)->send($mailable);
                $this->log($order, $address, 'sent', $mailable->envelope()->subject);
            } catch (Throwable $e) {
                report($e);
                $this->log($order, $address, 'failed', error: $e->getMessage());
            }
        }
    }

    private function log(ServiceOrder $order, string $address, string $status, ?string $message = null, ?string $error = null): void
    {
        NotificationLog::create([
            'service_order_id' => $order->getKey(),
            'channel' => 'email',
            'type' => self::TYPE,
            'recipient' => $address,
            'status' => $status,
            'message' => $message,
            'error' => $error,
        ]);
    }
}

<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderReceived;
use App\Models\PaymentGatewayLog;
use App\Models\ServiceOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one place an order's payment state changes. Every path that learns about a payment
 * (browser return, webhook, expiry sweep, late-payment recovery) settles the order here, under
 * a row lock, so the same payment can never be applied, receipted or announced twice.
 */
class OrderPaymentService
{
    // What a "paid" answer means for an order in its current state, as named outcomes so the
    // sweeps can report them.
    public const ACTION_NONE = 'none';

    public const ACTION_CONFIRM = 'confirm';                  // pending payment -> received

    public const ACTION_RECOVER = 'recover';                  // cancelled by the system -> received

    public const ACTION_NEEDS_REFUND = 'needs_refund';        // paid, but the order was cancelled on purpose

    /** Cancellation sources a late payment may revive. */
    private const REVIVABLE_SOURCES = ['system', 'gateway'];

    public function decidePaidAction(ServiceOrder $order): string
    {
        if ($order->isPaid()) {
            return self::ACTION_NONE;
        }

        return match ($order->status) {
            OrderStatus::PendingPayment => self::ACTION_CONFIRM,
            OrderStatus::Cancelled => in_array($order->cancellation_source, self::REVIVABLE_SOURCES, true)
                ? self::ACTION_RECOVER
                : self::ACTION_NEEDS_REFUND,
            default => self::ACTION_NONE,
        };
    }

    /**
     * Apply a confirmed payment to the order. Safe to call any number of times for the same
     * payment: only the first one changes anything.
     *
     * @return string one of the ACTION_* constants
     */
    public function applyPaid(ServiceOrder $order, PaymentGateway $gateway, ?string $reference = null): string
    {
        $action = DB::transaction(function () use ($order, $gateway, $reference) {
            $locked = ServiceOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return self::ACTION_NONE;
            }

            $action = $this->decidePaidAction($locked);

            if ($action === self::ACTION_NONE) {
                return $action;
            }

            $attributes = [
                'payment_status' => PaymentStatus::Paid,
                'payment_method' => $gateway,
                'payment_reference' => $reference ?? $locked->payment_reference,
                'paid_at' => now(),
            ];

            if ($action !== self::ACTION_NEEDS_REFUND) {
                $attributes += ['status' => OrderStatus::New, 'cancelled_at' => null, 'cancellation_source' => null];
            } else {
                // The money is in but the order was cancelled on purpose: keep it cancelled and
                // flag it, so it shows up for a manual refund and later sweeps leave it alone.
                $attributes['meta'] = ($locked->meta ?? []) + ['needs_refund' => true];
            }

            $this->saveWithReceipt($locked, $attributes);

            $locked->recordEvent(
                $action === self::ACTION_NEEDS_REFUND ? 'paid_needs_refund' : 'paid',
                __('orders.events.paid'),
                ['gateway' => $gateway->value, 'reference' => $reference],
                public: $action !== self::ACTION_NEEDS_REFUND,
            );

            if ($action === self::ACTION_RECOVER) {
                PaymentGatewayLog::log($locked, $gateway->value, 'late_payment_recovered', ['order' => $locked->order_number], ['outcome' => 'success', 'reference' => $reference]);
            }

            if ($action === self::ACTION_NEEDS_REFUND) {
                PaymentGatewayLog::log($locked, $gateway->value, 'paid_needs_manual_action', ['order' => $locked->order_number], ['outcome' => 'error', 'reason' => $action, 'reference' => $reference]);
                Log::critical('Payment captured for a cancelled order that could not be revived: refund or reinstate it', [
                    'order' => $locked->order_number,
                    'gateway' => $gateway->value,
                    'reference' => $reference,
                ]);
            }

            return $action;
        });

        $order->refresh();

        if (in_array($action, [self::ACTION_CONFIRM, self::ACTION_RECOVER], true)) {
            ServiceOrderReceived::dispatch($order);
        }

        return $action;
    }

    /** The customer's attempt failed (declined, cancelled on the gateway). A paid order is never downgraded. */
    public function markFailed(ServiceOrder $order, PaymentGateway $gateway, ?string $reason = null): void
    {
        $affected = ServiceOrder::query()
            ->whereKey($order->getKey())
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereIn('payment_status', [PaymentStatus::Pending->value, PaymentStatus::Failed->value])
            ->update(['payment_status' => PaymentStatus::Failed->value, 'payment_method' => $gateway->value]);

        if ($affected) {
            $order->refresh()->recordEvent('payment_failed', __('orders.events.payment_failed'), ['gateway' => $gateway->value, 'reason' => $reason]);
        }
    }

    /**
     * Give up on an unpaid order. Only a pending one is touched: a paid order is never
     * cancelled here.
     */
    public function cancelPending(ServiceOrder $order, string $source): bool
    {
        $affected = ServiceOrder::query()
            ->whereKey($order->getKey())
            ->where('status', OrderStatus::PendingPayment->value)
            ->where('payment_status', '!=', PaymentStatus::Paid->value)
            ->update([
                'status' => OrderStatus::Cancelled->value,
                'payment_status' => PaymentStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancellation_source' => $source,
            ]);

        if ($affected) {
            $order->refresh()->recordEvent('cancelled', __('orders.events.cancelled'), ['source' => $source]);
        }

        return (bool) $affected;
    }

    /**
     * Receipt numbers run RC-<year>-000001, 000002... A unique index backs the count, and a
     * clash between two simultaneous payments simply retries with the next number.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function saveWithReceipt(ServiceOrder $order, array $attributes): void
    {
        $existing = $order->receipt_number;

        for ($attempt = 1; ; $attempt++) {
            try {
                $order->forceFill($attributes + ['receipt_number' => $existing ?? $this->nextReceiptNumber()])->save();

                return;
            } catch (QueryException $e) {
                if ($attempt >= 5 || ! str_contains($e->getMessage(), 'receipt_number')) {
                    throw $e;
                }
            }
        }
    }

    private function nextReceiptNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "RC-{$year}-";

        $last = ServiceOrder::query()
            ->where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}

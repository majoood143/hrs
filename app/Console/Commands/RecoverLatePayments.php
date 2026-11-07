<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\ServiceOrder;
use App\Services\Payments\PaymentGateways;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecoverLatePayments extends Command
{
    protected $signature = 'orders:recover-late-payments
                            {--hours= : How far back to look; a Thawani session stays payable for 24 hours, and OMPAY can confirm a payment after its bank answers late}
                            {--full : Re-check every candidate, not just the recently cancelled ones}';

    protected $description = 'Re-check the gateway for orders cancelled by the expiry sweep whose checkout session may have been paid afterwards, and settle the ones that were.';

    // Cancelled this recently, an order is re-checked on every run (the customer is most likely
    // still on the hosted page); older ones only once an hour, to keep gateway API calls down.
    private const HOT_WINDOW_MINUTES = 120;

    public function handle(PaymentGateways $gateways): int
    {
        $hours = max(1, (int) ($this->option('hours') ?: config('payments.late_payment_window_hours', 24)));
        $hourlyRun = now()->minute < 5 || $this->option('full');

        $ids = ServiceOrder::query()
            ->where('status', OrderStatus::Cancelled->value)
            ->whereIn('payment_method', [PaymentGateway::Thawani->value, PaymentGateway::Ompay->value])
            ->whereNotNull('payment_session_id')
            ->whereIn('cancellation_source', ['system', 'gateway'])
            ->where('payment_status', '!=', PaymentStatus::Paid->value)
            ->where('created_at', '>=', now()->subHours($hours))
            ->when(! $hourlyRun, fn ($query) => $query->where('cancelled_at', '>=', now()->subMinutes(self::HOT_WINDOW_MINUTES)))
            ->pluck('id');

        $settled = 0;

        foreach ($ids as $id) {
            $order = ServiceOrder::find($id);

            // with the keys the session was made with: ours, or a stable's own account
            $bound = $order ? $gateways->bind($gateways->get($order->payment_method), $order) : null;

            if (! $order || ! $bound->isConfigured()) {
                continue;
            }

            try {
                $bound->reconcile($order);
            } catch (Throwable $e) {
                Log::warning('orders:recover-late-payments: payment check failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);

                continue;
            }

            if ($order->refresh()->isPaid()) {
                $settled++;
            }
        }

        $this->info("Checked {$ids->count()} order(s); {$settled} had been paid.");

        return self::SUCCESS;
    }
}

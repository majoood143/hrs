<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\ServiceOrder;
use App\Services\Payments\Gateways\ThawaniGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecoverLatePayments extends Command
{
    protected $signature = 'orders:recover-late-payments
                            {--hours= : How far back to look; a Thawani session stays payable for 24 hours}
                            {--full : Re-check every candidate, not just the recently cancelled ones}';

    protected $description = 'Re-check Thawani for orders cancelled by the expiry sweep whose checkout session may have been paid afterwards, and settle the ones that were.';

    // Cancelled this recently, an order is re-checked on every run (the customer is most likely
    // still on the hosted page); older ones only once an hour, to keep Thawani API calls down.
    private const HOT_WINDOW_MINUTES = 120;

    public function handle(ThawaniGateway $thawani): int
    {
        if (! $thawani->isConfigured()) {
            return self::SUCCESS;
        }

        $hours = max(1, (int) ($this->option('hours') ?: config('payments.late_payment_window_hours', 24)));
        $hourlyRun = now()->minute < 5 || $this->option('full');

        $ids = ServiceOrder::query()
            ->where('status', OrderStatus::Cancelled->value)
            ->where('payment_method', PaymentGateway::Thawani->value)
            ->whereNotNull('payment_session_id')
            ->whereIn('cancellation_source', ['system', 'gateway'])
            ->where('payment_status', '!=', PaymentStatus::Paid->value)
            ->where('created_at', '>=', now()->subHours($hours))
            ->when(! $hourlyRun, fn ($query) => $query->where('cancelled_at', '>=', now()->subMinutes(self::HOT_WINDOW_MINUTES)))
            ->pluck('id');

        $settled = 0;

        foreach ($ids as $id) {
            $order = ServiceOrder::find($id);

            if (! $order) {
                continue;
            }

            try {
                $thawani->reconcile($order);
            } catch (Throwable $e) {
                Log::warning('orders:recover-late-payments: Thawani session check failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);

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

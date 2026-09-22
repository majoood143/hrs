<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\ServiceOrder;
use App\Services\Payments\Gateways\ThawaniGateway;
use App\Services\Payments\OrderPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Cancel service orders that were never paid, after checking their gateway session one last time.';

    public function handle(ThawaniGateway $thawani, OrderPaymentService $payments): int
    {
        $cutoff = now()->subMinutes(max(1, (int) config('payments.pending_ttl_minutes', 30)));

        $orders = ServiceOrder::query()
            ->where('status', OrderStatus::PendingPayment->value)
            ->where('payment_status', '!=', PaymentStatus::Paid->value)
            ->where('created_at', '<', $cutoff)
            ->get();

        $cancelled = 0;
        $settled = 0;

        foreach ($orders as $order) {
            // A Thawani session outlives our hold, so a customer may still be paying: ask first.
            if ($order->payment_method === PaymentGateway::Thawani && $order->payment_session_id && $thawani->isConfigured()) {
                try {
                    $thawani->reconcile($order);
                } catch (Throwable $e) {
                    // Cannot tell whether it was paid: leave it for the next run rather than cancel a paid order.
                    Log::warning('orders:expire-pending: Thawani session check failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);

                    continue;
                }

                if ($order->refresh()->isPaid()) {
                    $settled++;

                    continue;
                }
            }

            $cancelled += $payments->cancelPending($order, 'system') ? 1 : 0;
        }

        $this->info("Cancelled {$cancelled} unpaid order(s); {$settled} turned out to be paid.");

        return self::SUCCESS;
    }
}

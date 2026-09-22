<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every gateway session/payment id an order has ever been given, so a late callback or webhook
 * for a superseded session (the order moved on to a newer one) can still be matched back to it.
 * See database/migrations/2026_09_28_000002_create_payment_gateway_sessions_table.php.
 */
class PaymentGatewaySession extends Model
{
    protected $fillable = ['service_order_id', 'gateway', 'session_id'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    /** Record a session id for an order, once (safe to call again for the same pair). */
    public static function record(ServiceOrder $order, string $gateway, string $sessionId): void
    {
        static::query()->firstOrCreate(
            ['gateway' => $gateway, 'session_id' => $sessionId],
            ['service_order_id' => $order->getKey()],
        );
    }

    /** The order a session id was issued for, current or historical. */
    public static function findOrder(string $gateway, string $sessionId): ?ServiceOrder
    {
        return static::query()->where('gateway', $gateway)->where('session_id', $sessionId)->first()?->order;
    }
}

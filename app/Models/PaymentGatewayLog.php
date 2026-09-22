<?php

namespace App\Models;

use App\Support\ClassifiesGatewayOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request / response pair with a payment gateway, kept for investigation. Rows are
 * never edited or deleted from the admin.
 */
class PaymentGatewayLog extends Model
{
    use ClassifiesGatewayOutcome;

    protected $fillable = [
        'service_order_id',
        'gateway',
        'event',
        'status_code',
        'request_payload',
        'response_payload',
        'outcome',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    /** Payload keys that must never reach the log. */
    private const REDACTED = [
        'password', 'tranportal_password', 'secret_key', 'working_key', 'resource_key', 'webhook_secret',
        // Card data a gateway callback may echo back (its response shape is not fully
        // documented for every gateway): never store it, masked or not.
        'cardno', 'card_no', 'cardnumber', 'card_number', 'pan', 'cvv', 'cvv2', 'cardexpiry',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    /**
     * Record a gateway request / response pair. The order may be missing (a callback we
     * could not match to one is still worth keeping).
     */
    public static function log(?ServiceOrder $order, string $gateway, string $event, mixed $request = null, mixed $response = null, ?int $statusCode = null): self
    {
        $response = static::redact($response);

        return static::create([
            'service_order_id' => $order?->getKey(),
            'gateway' => $gateway,
            'event' => $event,
            'status_code' => $statusCode,
            'request_payload' => static::redact($request),
            'response_payload' => $response,
            // Classified once, here, and never touched again (rows are never edited); this is
            // what makes filtering/counting by outcome a plain indexed equality match instead of
            // a MySQL-only JSON expression evaluated per row (see ClassifiesGatewayOutcome).
            'outcome' => static::classify($gateway, is_array($response) ? $response : []),
        ]);
    }

    private static function redact(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED, true)) {
                $payload[$key] = '••••••••';
            } elseif (is_array($value)) {
                $payload[$key] = static::redact($value);
            }
        }

        return $payload;
    }
}

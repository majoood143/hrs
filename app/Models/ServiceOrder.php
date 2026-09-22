<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Services\Orders\OrderMoney;
use DeviceDetector\DeviceDetector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Packstub\FormBuilder\FormBuilder;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A customer's request for a service, usually created from a submitted form.
 *
 * Money (all OMR, 3 decimals): total = price + fee_amount + vat_on_price + vat_on_fee.
 * price + vat_on_price is the client's share (and what a refund returns); fee_amount +
 * vat_on_fee is ours. The figures are a snapshot: editing a service or fee rule later
 * never changes an existing order.
 */
class ServiceOrder extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $attributes = [
        'status' => 'pending_payment',
        'payment_status' => 'pending',
        'locale' => 'en',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentGateway::class,
            'requires_document' => 'boolean',
            'price' => 'decimal:3',
            'fee_amount' => 'decimal:3',
            'vat_rate' => 'decimal:2',
            'vat_on_price' => 'decimal:3',
            'vat_on_fee' => 'decimal:3',
            'total' => 'decimal:3',
            'service_fee_value' => 'decimal:3',
            'commission_amount' => 'decimal:3',
            'commission_value' => 'decimal:3',
            'vat_on_commission' => 'decimal:3',
            'refunded_amount' => 'decimal:3',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->order_number ??= static::newOrderNumber();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'payment_method', 'assigned_to', 'refunded_amount', 'paid_at', 'completed_at', 'cancelled_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ServiceOrder');
    }

    /**
     * SO-XXXXXXXX from an alphabet without look-alike characters (0/O, 1/I). It carries no
     * information and is random, so it can be printed and quoted on the phone.
     */
    public static function newOrderNumber(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $number = 'SO-'.collect(range(1, 8))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (static::query()->where('order_number', $number)->exists());

        return $number;
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormBuilder::formModel(), 'form_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormBuilder::submissionModel(), 'submission_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(OrderStage::class, 'service_order_id')->orderBy('position');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class, 'service_order_id')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OrderDocument::class, 'service_order_id')->orderBy('id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'service_order_id')->latest('id');
    }

    public function gatewayLogs(): HasMany
    {
        return $this->hasMany(PaymentGatewayLog::class);
    }

    public function gatewaySessions(): HasMany
    {
        return $this->hasMany(PaymentGatewaySession::class);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', PaymentStatus::Paid->value);
    }

    // ------------------------------------------------------------------
    // Money
    // ------------------------------------------------------------------

    public function vatAmount(): float
    {
        return round((float) $this->vat_on_price + (float) $this->vat_on_fee, 3);
    }

    /** The client's share: what the service costs, with its VAT. */
    public function clientShare(): float
    {
        return round((float) $this->price + (float) $this->vat_on_price, 3);
    }

    /** Ours: the service fee and its VAT. */
    public function feeShare(): float
    {
        return round((float) $this->fee_amount + (float) $this->vat_on_fee, 3);
    }

    /** The commission taken from the client's share, less the part that went back to the customer in a refund. */
    public function earnedCommission(): float
    {
        return OrderMoney::earnedCommission(
            (int) round((float) $this->commission_amount * 1000),
            (int) round($this->clientShare() * 1000),
            (int) round((float) $this->refunded_amount * 1000),
        ) / 1000;
    }

    /** The VAT on the commission, less the part that went back in a refund (it shrinks with the commission). */
    public function earnedVatOnCommission(): float
    {
        return OrderMoney::earnedCommission(
            (int) round((float) $this->vat_on_commission * 1000),
            (int) round($this->clientShare() * 1000),
            (int) round((float) $this->refunded_amount * 1000),
        ) / 1000;
    }

    /** Everything this order owes us: the service fee, the VAT on it, the commission earned, and the VAT on that. */
    public function dueToUs(): float
    {
        return round($this->feeShare() + $this->earnedCommission() + $this->earnedVatOnCommission(), 3);
    }

    /** A paid order that will not be fulfilled (rejected or cancelled) and has not been refunded yet. */
    public function refundDue(): bool
    {
        return $this->isPaid()
            && in_array($this->status, [OrderStatus::Rejected, OrderStatus::Cancelled], true)
            && $this->refundableAmount() > 0;
    }

    /** The reviewer's reason when the request was rejected, for the customer to read. */
    public function rejectionReason(): ?string
    {
        return $this->stages->firstWhere('status', OrderStage::REJECTED)?->comment;
    }

    /** What a refund may still return: the client's share less what was already refunded. */
    public function refundableAmount(): float
    {
        if (! $this->isPaid()) {
            return 0.0;
        }

        return max(0.0, round($this->clientShare() - (float) $this->refunded_amount, 3));
    }

    /** The total in baisa, the unit gateways compare amounts in. */
    public function totalBaisa(): int
    {
        return (int) round((float) $this->total * 1000);
    }

    // ------------------------------------------------------------------
    // State
    // ------------------------------------------------------------------

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function isFree(): bool
    {
        return $this->payment_status === PaymentStatus::Free;
    }

    /** Whether the customer can still be sent to a gateway for this order. */
    public function isPayable(): bool
    {
        return $this->status === OrderStatus::PendingPayment
            && in_array($this->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true);
    }

    public function customerName(): string
    {
        return $this->customer_name ?: __('orders.guest');
    }

    public function recordEvent(string $type, ?string $message = null, array $data = [], bool $public = true, ?int $userId = null): OrderEvent
    {
        return $this->events()->create([
            'type' => $type,
            'message' => $message,
            'data' => $data ?: null,
            'is_public' => $public,
            'user_id' => $userId,
        ]);
    }

    /** The order number without separators, for gateways that only take alphanumeric references. */
    public function compactNumber(): string
    {
        return Str::of($this->order_number)->replaceMatches('/[^A-Za-z0-9]/', '')->toString();
    }

    // Parses the stored user agent with matomo/device-detector into a display-ready
    // breakdown of device, OS, and client info, or a bot flag if the UA is a known bot.
    public function getDeviceInfo(): array
    {
        if (empty($this->user_agent)) {
            return ['available' => false];
        }

        $dd = new DeviceDetector($this->user_agent);
        $dd->parse();

        if ($dd->isBot()) {
            $bot = $dd->getBot() ?: [];

            return [
                'available' => true,
                'is_bot' => true,
                'bot_name' => $bot['name'] ?? null,
                'bot_category' => $bot['category'] ?? null,
                'bot_producer' => $bot['producer']['name'] ?? null,
            ];
        }

        $client = $dd->getClient() ?: [];
        $os = $dd->getOs() ?: [];

        return [
            'available' => true,
            'is_bot' => false,
            'device_type' => $dd->getDeviceName() ?: null,
            'brand' => $dd->getBrandName() ?: null,
            'model' => $dd->getModel() ?: null,
            'is_mobile' => $dd->isMobile(),
            'is_desktop' => $dd->isDesktop(),
            'client_type' => $client['type'] ?? null,
            'client_name' => $client['name'] ?? null,
            'client_version' => $client['version'] ?? null,
            'client_engine' => $client['engine'] ?? null,
            'client_engine_version' => $client['engine_version'] ?? null,
            'os_name' => $os['name'] ?? null,
            'os_version' => $os['version'] ?? null,
            'os_platform' => $os['platform'] ?? null,
        ];
    }
}

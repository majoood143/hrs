<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Models\Concerns\LogsStableActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stable's own gateway account (Thawani, NBO or CCAvenue), for stables that take their bookings'
 * money themselves. The keys are stored encrypted with the app key, and are only used once an admin
 * approves them; changing any of them sends the account back for approval.
 */
class StablePaymentAccount extends Model
{
    use LogsStableActivity;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** The key fields of each gateway; the secret ones are never shown back in full. */
    public const FIELDS = [
        'thawani' => ['secret_key' => true, 'publishable_key' => false, 'webhook_secret' => true],
        'nbo' => ['tranportal_id' => false, 'tranportal_password' => true, 'resource_key' => true, 'endpoint_url' => false],
        'ccavenue' => ['merchant_id' => false, 'access_code' => false, 'working_key' => true, 'endpoint_url' => false],
    ];

    /** Fields a gateway works without (the rest are required). */
    public const OPTIONAL = ['webhook_secret', 'endpoint_url'];

    protected $fillable = ['stable_id', 'gateway', 'credentials', 'test_mode'];

    protected $attributes = [
        'status' => self::PENDING,
        'test_mode' => true,
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'credentials' => 'encrypted:array',
            'test_mode' => 'boolean',
            'reviewed_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'last_test_ok' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // new keys, a new gateway or leaving test mode must be looked at again by an admin
        static::saving(function (self $account): void {
            if ($account->exists && $account->isDirty(['credentials', 'gateway', 'test_mode']) && ! $account->isDirty('status')) {
                $account->status = self::PENDING;
                $account->reviewed_at = null;
                $account->reviewed_by = null;
            }
        });
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    public function get(string $key): string
    {
        return trim((string) ($this->credentials[$key] ?? ''));
    }

    /** Every required key is filled in. */
    public function isComplete(): bool
    {
        $fields = self::FIELDS[$this->gateway?->value] ?? [];

        foreach (array_keys($fields) as $key) {
            if (! in_array($key, self::OPTIONAL, true) && $this->get($key) === '') {
                return false;
            }
        }

        return $fields !== [];
    }

    /** "sk_live_…a1b2": enough to recognise a key without showing it. */
    public function masked(string $key): string
    {
        $value = $this->get($key);

        if ($value === '') {
            return '—';
        }

        return (self::FIELDS[$this->gateway?->value][$key] ?? false)
            ? str_repeat('•', 6).substr($value, -4)
            : $value;
    }

    /** @return list<string> the gateways a stable may use for its own account */
    public static function gateways(): array
    {
        return array_keys(self::FIELDS);
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['gateway', 'test_mode', 'status', 'review_note'];
    }
}

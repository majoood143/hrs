<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Says whether a gateway log row's response was a success, a failure, still pending, an error,
 * or unknown, per gateway. classify() is the one place this is worked out; PaymentGatewayLog::log()
 * calls it once to populate the persisted, indexed `outcome` column, so filtering by outcome is a
 * plain equality match rather than a MySQL-only JSON expression evaluated per row.
 *
 * A row may also say so itself with `{"outcome": "success|failed|pending|error"}`, which is
 * how our own events (late-payment recovery, amount mismatch...) are logged; it wins over
 * the gateway-specific rules.
 */
trait ClassifiesGatewayOutcome
{
    public const OUTCOMES = ['success', 'failed', 'pending', 'error', 'unknown'];

    /**
     * The persisted value when there is one (every row PaymentGatewayLog::log() creates); falls
     * back to classifying live for a transient/unsaved instance (a common way to unit-test the
     * classification rules themselves) or a row that somehow predates the column.
     */
    public function getOutcomeAttribute(?string $value): string
    {
        return $value ?? static::classify($this->gateway, $this->response_payload ?? []);
    }

    /** @param  array<string, mixed>  $payload */
    public static function classify(?string $gateway, array $payload): string
    {
        $own = $payload['outcome'] ?? null;

        if (is_string($own) && in_array($own, self::OUTCOMES, true)) {
            return $own;
        }

        return match ($gateway) {
            'thawani' => static::thawaniOutcome($payload),
            'nbo' => static::nboOutcome($payload),
            'ccavenue' => static::ccavenueOutcome($payload),
            'demo' => static::demoOutcome($payload),
            default => 'unknown',
        };
    }

    private static function thawaniOutcome(array $payload): string
    {
        if (array_key_exists('error', $payload)) {
            return 'error';
        }

        $status = $payload['data']['payment_status'] ?? null;

        return match (true) {
            $status === 'paid' => 'success',
            $status !== null => 'failed',
            default => 'unknown',
        };
    }

    private static function nboOutcome(array $payload): string
    {
        $error = $payload['error'] ?? null;

        if ($error !== null && $error !== '' && $error !== '0') {
            return $error === 'no_transaction_data' ? 'error' : 'failed';
        }

        $result = $payload['result'] ?? null;

        return match (true) {
            in_array($result, ['CAPTURED', 'APPROVED'], true) => 'success',
            $result !== null => 'failed',
            default => 'unknown',
        };
    }

    private static function ccavenueOutcome(array $payload): string
    {
        if (array_key_exists('error', $payload)) {
            return 'error';
        }

        $status = strtolower((string) ($payload['order_status'] ?? ''));

        return match (true) {
            $status === 'success' => 'success',
            in_array($status, ['failure', 'aborted', 'invalid', 'unsuccessful'], true) => 'failed',
            $status === 'initiated' => 'pending',
            default => 'unknown',
        };
    }

    private static function demoOutcome(array $payload): string
    {
        return match ($payload['status'] ?? null) {
            'approved' => 'success',
            'declined' => 'failed',
            default => 'unknown',
        };
    }

    public function scopeOutcome(Builder $query, string $outcome): Builder
    {
        return $query->where('outcome', $outcome);
    }

    public function scopeSearchPayloads(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->whereRaw('CAST(request_payload AS CHAR) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('CAST(response_payload AS CHAR) LIKE ?', ["%{$term}%"]);
        });
    }
}

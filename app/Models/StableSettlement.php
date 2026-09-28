<?php

namespace App\Models;

use App\Models\Concerns\LogsStableActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payout to a stable (we paid it its share) or a receipt from it (it paid us our fees and
 * commission), recorded by an admin once the money has actually moved.
 */
class StableSettlement extends Model
{
    use LogsStableActivity;

    public const PAYOUT = 'payout';

    public const RECEIPT = 'receipt';

    public const METHODS = ['bank_transfer', 'cash', 'cheque', 'other'];

    protected $fillable = ['stable_id', 'direction', 'amount', 'paid_on', 'method', 'reference', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'paid_on' => 'date',
        ];
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['direction', 'amount', 'paid_on', 'method', 'reference'];
    }

    /** Stored as a plain "Y-m-d" so date ranges compare the same on MySQL and the sqlite tests. */
    public function setPaidOnAttribute(mixed $value): void
    {
        $this->attributes['paid_on'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function amountBaisa(): int
    {
        return (int) round((float) $this->amount * 1000);
    }

    /** How it moves the balance (positive = we owe the stable): a payout lowers it, a receipt raises it. */
    public function effectOnBalance(): int
    {
        return $this->direction === self::PAYOUT ? -$this->amountBaisa() : $this->amountBaisa();
    }
}

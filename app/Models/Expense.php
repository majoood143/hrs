<?php

namespace App\Models;

use App\Models\Concerns\LogsStableActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Money spent: ours when stable_id is null (admin → Payments → Expenses, part of our income report),
 * else a stable's own (its panel and the app's My stable), never part of our books. Only while
 * General Settings → Modules → Expenses is on (`enabled()`).
 */
class Expense extends Model
{
    use LogsStableActivity;
    use SoftDeletes;

    /** Receipts live on the private disk; links to them are signed. */
    public const DISK = 'local';

    public const RECEIPT_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public const RECEIPT_MAX_KB = 5120;

    protected $fillable = [
        'stable_id',
        'expense_category_id',
        'stable_horse_id',
        'spent_on',
        'amount',
        'vendor',
        'note',
        'receipt',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'decimal:3',
        ];
    }

    /** General Settings → Modules → Expenses (off until an admin turns it on). */
    public static function enabled(): bool
    {
        return (bool) SiteSetting::get('module_expenses_enabled', false);
    }

    protected static function booted(): void
    {
        // the receipt goes with the expense once it is gone for good (soft deletes keep it)
        static::forceDeleted(fn (self $expense) => $expense->receipt && Storage::disk(self::DISK)->delete($expense->receipt));
    }

    /** Stored as a plain "Y-m-d" so date ranges compare the same on MySQL and the sqlite tests. */
    public function setSpentOnAttribute(mixed $value): void
    {
        $this->attributes['spent_on'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(StableHorse::class, 'stable_horse_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('stable_id');
    }

    public function baisa(): int
    {
        return (int) round((float) $this->amount * 1000);
    }

    /** A signed link to the receipt, good for 30 minutes. */
    public function receiptUrl(): ?string
    {
        return $this->receipt ? Storage::disk(self::DISK)->temporaryUrl($this->receipt, now()->addMinutes(30)) : null;
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['expense_category_id', 'stable_horse_id', 'spent_on', 'amount', 'vendor', 'note', 'receipt'];
    }
}

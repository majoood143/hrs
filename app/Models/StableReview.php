<?php

namespace App\Models;

use App\Models\Concerns\LogsStableActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's rating (1–5) and comment on a session they attended, one per booking. Shown on the
 * stable's page unless an admin hides it; the stable may reply.
 */
class StableReview extends Model
{
    use LogsStableActivity;

    /** @var list<string> */
    protected static $recordEvents = ['updated'];

    protected $guarded = [];

    protected $attributes = [
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_visible' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(StableBooking::class, 'stable_booking_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(StableOffering::class, 'stable_offering_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /** "Aisha A.": the first name and an initial, never the full name. */
    public function displayName(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->author_name)) ?: [];
        $first = $parts[0] ?? '';

        if ($first === '') {
            return __('stable_reviews.anonymous');
        }

        return isset($parts[1]) ? $first.' '.mb_substr($parts[1], 0, 1).'.' : $first;
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['reply', 'is_visible'];
    }
}

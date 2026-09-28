<?php

namespace App\Models;

use App\Models\Concerns\LogsStableActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Days a stable (or just one of its offerings, when stable_offering_id is set) takes no bookings. */
class StableClosure extends Model
{
    use LogsStableActivity;

    protected $fillable = [
        'stable_id',
        'stable_offering_id',
        'starts_on',
        'ends_on',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(StableOffering::class, 'stable_offering_id');
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['stable_offering_id', 'starts_on', 'ends_on', 'reason'];
    }
}

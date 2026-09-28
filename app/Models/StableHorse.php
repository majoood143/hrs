<?php

namespace App\Models;

use App\Models\Concerns\LogsStableActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A school horse a stable rides lessons on (optional, "staff" in the stable settings). */
class StableHorse extends Model
{
    use LogsStableActivity;

    protected $fillable = ['stable_id', 'name', 'notes', 'photo', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['name', 'notes', 'photo', 'is_active'];
    }
}

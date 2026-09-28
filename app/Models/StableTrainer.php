<?php

namespace App\Models;

use App\Models\Concerns\LogsStableActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A trainer a stable can put on a schedule or a single slot (optional, "staff" in the stable settings). */
class StableTrainer extends Model
{
    use LogsStableActivity;

    protected $fillable = ['stable_id', 'en_name', 'ar_name', 'phone', 'photo', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' && filled($this->ar_name) ? $this->ar_name : (string) $this->en_name;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['en_name', 'ar_name', 'phone', 'photo', 'is_active'];
    }
}

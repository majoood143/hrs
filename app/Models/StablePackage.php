<?php

namespace App\Models;

use App\Models\Concerns\LogsStableActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A number of sessions of one service sold together, to be used within validity_days of paying. */
class StablePackage extends Model
{
    use LogsStableActivity;

    protected $fillable = [
        'stable_id', 'stable_offering_id', 'en_name', 'ar_name', 'en_description', 'ar_description',
        'sessions', 'price', 'validity_days', 'is_active',
    ];

    protected $attributes = [
        'validity_days' => 60,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'sessions' => 'integer',
            'price' => 'decimal:3',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
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

    public function purchases(): HasMany
    {
        return $this->hasMany(StablePackagePurchase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' && filled($this->ar_name) ? $this->ar_name : (string) $this->en_name;
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' && filled($this->ar_description) ? $this->ar_description : $this->en_description;
    }

    /** What the sessions would cost one by one, in baisa, and how much the package saves on that. */
    public function savingBaisa(): int
    {
        $single = (int) round((float) $this->offering?->price * 1000) * $this->sessions;

        return max(0, $single - (int) round((float) $this->price * 1000));
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['stable_offering_id', 'en_name', 'ar_name', 'sessions', 'price', 'validity_days', 'is_active'];
    }
}

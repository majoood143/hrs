<?php

namespace App\Models;

use App\Models\Concerns\RefusesDeleteWhileReferenced;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A service a stable can tick in its profile (admin → Stable Services). A bookable one can also be
 * the type of a stable's offering (what customers book by the slot) and is a booking search filter.
 */
class StableService extends Model
{
    use RefusesDeleteWhileReferenced;

    /** What refers to a service: `RefusesDeleteWhileReferenced` refuses to delete one still in use. */
    public const DELETE_REFERENCES = ['stable_offerings' => 'stable_service_id'];

    protected $fillable = ['en_name', 'ar_name', 'is_bookable'];

    protected function casts(): array
    {
        return ['is_bookable' => 'boolean'];
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(StableOffering::class);
    }

    public function stables(): BelongsToMany
    {
        return $this->belongsToMany(Stable::class, 'stable_stable_service');
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_bookable', true);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    /** The booking search's type filter: every bookable service, by name. @return array<int, string> */
    public static function bookableOptions(): array
    {
        return static::query()->bookable()->get()->sortBy('name')->mapWithKeys(fn (self $service) => [$service->id => $service->name])->all();
    }
}

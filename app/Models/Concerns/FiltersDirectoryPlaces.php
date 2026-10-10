<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * The listing filters of the directory places (stables, clinics, centers, shops), named like the
 * website's query string and shared by its `_listing` partials and the mobile API.
 */
trait FiltersDirectoryPlaces
{
    /** Sort options; the first is the default. */
    public const SORTS = ['name', 'newest'];

    /**
     * @param  array<string, mixed>  $filters  type, service_id, country_id, region_id, city_id
     */
    public function scopeFilteredBy(Builder $query, array $filters): Builder
    {
        $types = defined(static::class.'::TYPES') ? array_keys(static::TYPES) : [];
        $type = in_array($filters['type'] ?? null, $types, true) ? $filters['type'] : null;
        $serviceTable = $this->services()->getRelated()->getTable();

        return $query
            ->when($type, fn (Builder $q, string $v) => $q->where('type', $v))
            ->when((int) ($filters['country_id'] ?? 0), fn (Builder $q, int $v) => $q->where('country_id', $v))
            ->when((int) ($filters['region_id'] ?? 0), fn (Builder $q, int $v) => $q->where('region_id', $v))
            ->when((int) ($filters['city_id'] ?? 0), fn (Builder $q, int $v) => $q->where('city_id', $v))
            ->when(
                (int) ($filters['service_id'] ?? 0),
                fn (Builder $q, int $v) => $q->whereHas('services', fn (Builder $services) => $services->where($serviceTable.'.id', $v)),
            );
    }

    public function scopeSortedBy(Builder $query, ?string $sort): Builder
    {
        return $sort === 'newest' ? $query->latest('id') : $query->orderBy('en_name');
    }
}

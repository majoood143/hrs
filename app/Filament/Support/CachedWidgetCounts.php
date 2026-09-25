<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard counts, cached for a few minutes and read in one query per table.
 *
 * Used by the dashboard widgets together with `$pollingInterval = null` (Filament otherwise
 * re-runs every widget every 5 seconds for every open dashboard tab). The figures may be up to
 * TTL_MINUTES old; the dashboard is an overview, not a live counter.
 */
class CachedWidgetCounts
{
    public const TTL_MINUTES = 5;

    /**
     * The total rows of a model and how many match `$column = $value` (the "active" ones).
     * Global scopes (soft deletes) apply, as they did with `Model::count()`.
     *
     * @param  class-string<Model>  $model
     * @return array{total: int, matching: int}
     */
    public static function totalAndMatching(string $model, string $column, mixed $value): array
    {
        $key = 'widgets.counts.'.class_basename($model).".{$column}";

        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), function () use ($model, $column, $value) {
            $row = $model::query()
                ->selectRaw('count(*) as total')
                ->selectRaw("sum(case when {$column} = ? then 1 else 0 end) as matching", [$value])
                ->toBase()
                ->first();

            return ['total' => (int) $row->total, 'matching' => (int) $row->matching];
        });
    }

    /**
     * Row counts of a model grouped by one column, e.g. horses per type_id.
     *
     * @param  class-string<Model>  $model
     * @return array<int|string, int>
     */
    public static function groupedBy(string $model, string $column): array
    {
        $key = 'widgets.counts.'.class_basename($model).".by.{$column}";

        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), fn () => $model::query()
            ->selectRaw("{$column} as grp, count(*) as total")
            ->groupBy($column)
            ->toBase()
            ->pluck('total', 'grp')
            ->map(fn ($total) => (int) $total)
            ->all());
    }

    /** Caches any other widget value (e.g. chart data) under the same TTL. */
    public static function remember(string $key, callable $callback): mixed
    {
        return Cache::remember('widgets.'.$key, now()->addMinutes(self::TTL_MINUTES), $callback);
    }
}

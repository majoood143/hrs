<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A lookup (country, region, city, horse type, gender, colour…) that refuses to be deleted while
 * anything still points at it.
 *
 * Many foreign keys cascade (a deleted country takes its regions, cities and every listing in
 * them; a deleted horse type takes every horse of that type with its transactions and papers),
 * so `delete()` returns false instead, which Filament's delete actions (single, bulk, relation
 * managers) report as a failure (`App\Filament\Support\InUseDeleteActions` explains it).
 *
 * Each model lists what refers to it in `DELETE_REFERENCES` (table => column or columns).
 */
trait RefusesDeleteWhileReferenced
{
    public static function bootRefusesDeleteWhileReferenced(): void
    {
        static::deleting(fn (self $model): bool => ! $model->isInUse());
    }

    public function isInUse(): bool
    {
        foreach (static::DELETE_REFERENCES as $table => $columns) {
            // A table missing here (a partial test schema) cannot refer to anything.
            if (! Schema::hasTable($table)) {
                continue;
            }

            $used = DB::table($table)
                ->where(function ($query) use ($columns) {
                    foreach ((array) $columns as $column) {
                        $query->orWhere($column, $this->getKey());
                    }
                })
                ->exists();

            if ($used) {
                return true;
            }
        }

        return false;
    }
}

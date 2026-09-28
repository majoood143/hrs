<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/** The admins told about a new stable: super admins and whoever may edit stables. */
final class StableApprovers
{
    /** @return list<string> */
    public static function emails(): array
    {
        try {
            $superAdmin = (string) config('filament-shield.super_admin.name', 'super_admin');

            return User::query()
                ->where('type', '!=', User::TYPE_STABLE_OWNER)
                ->whereNotNull('email')
                ->where(fn (Builder $q) => $q
                    ->whereHas('roles', fn (Builder $r) => $r->where('name', $superAdmin))
                    ->orWhereHas('roles.permissions', fn (Builder $p) => $p->where('name', 'Update:Stable'))
                    ->orWhereHas('permissions', fn (Builder $p) => $p->where('name', 'Update:Stable')))
                ->pluck('email')
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values()
                ->all();
        } catch (Throwable $e) {
            // an alert we cannot address must never fail the owner's registration
            report($e);

            return [];
        }
    }
}

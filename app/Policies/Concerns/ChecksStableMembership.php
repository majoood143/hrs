<?php

namespace App\Policies\Concerns;

use App\Models\Stable;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Stable owners have no Shield permissions: they may manage their own stable's records, and nothing
 * else. With a record, the record's stable decides; without one (list, create), the stable they are
 * working in (the /stable panel's current tenant) does.
 *
 * An admin who manages stables (User::managesStables()) counts as a member too, but only inside the
 * /stable panel and only for the stable open there: working on its owner's behalf, every change
 * logged. Everywhere else admins keep their Shield permissions.
 */
trait ChecksStableMembership
{
    protected function isStableMember(AuthUser $user, ?Model $record = null): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $stableId = $record === null ? null : ($record instanceof Stable ? $record->getKey() : $record->getAttribute('stable_id'));

        if ($user->isStableOwner()) {
            if ($record !== null) {
                return $user->belongsToStable($stableId);
            }

            $tenant = Filament::getTenant();

            return $tenant instanceof Stable && $user->belongsToStable($tenant->getKey());
        }

        return $this->actsForStable($user, $stableId);
    }

    /** An admin in the stable panel, on the stable open there. */
    private function actsForStable(User $user, int|string|null $stableId): bool
    {
        $tenant = Filament::getTenant();

        return Filament::getCurrentPanel()?->getId() === 'stable'
            && $tenant instanceof Stable
            && ($stableId === null || (int) $stableId === (int) $tenant->getKey())
            && $user->managesStables();
    }
}

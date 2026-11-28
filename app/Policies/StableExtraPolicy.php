<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StableExtra;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Stable owners for their own stable only; admins by their permissions on stables (extras are edited
 * from the stable's admin page, so there is no Shield permission of their own).
 */
class StableExtraPolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:Stable');
    }

    public function view(AuthUser $user, StableExtra $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:Stable');
    }

    public function create(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('Update:Stable');
    }

    public function update(AuthUser $user, StableExtra $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:Stable');
    }

    public function delete(AuthUser $user, StableExtra $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:Stable');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('Update:Stable');
    }
}

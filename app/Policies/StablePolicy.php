<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Stable;
use App\Models\User;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Admins by their Shield permissions. A stable owner may see and edit their own stables, and add another.
 * Hand-edited: regenerate Shield policies with --ignore-existing-policies, or this is overwritten.
 */
class StablePolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Stable');
    }

    public function view(AuthUser $authUser, Stable $stable): bool
    {
        return $this->isStableMember($authUser, $stable) || $authUser->can('View:Stable');
    }

    public function create(AuthUser $authUser): bool
    {
        return ($authUser instanceof User && $authUser->isStableOwner()) || $authUser->can('Create:Stable');
    }

    public function update(AuthUser $authUser, Stable $stable): bool
    {
        return $this->isStableMember($authUser, $stable) || $authUser->can('Update:Stable');
    }

    public function delete(AuthUser $authUser, Stable $stable): bool
    {
        return $authUser->can('Delete:Stable');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Stable');
    }

    public function restore(AuthUser $authUser, Stable $stable): bool
    {
        return $authUser->can('Restore:Stable');
    }

    public function forceDelete(AuthUser $authUser, Stable $stable): bool
    {
        return $authUser->can('ForceDelete:Stable');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Stable');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Stable');
    }

    public function replicate(AuthUser $authUser, Stable $stable): bool
    {
        return $authUser->can('Replicate:Stable');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Stable');
    }
}

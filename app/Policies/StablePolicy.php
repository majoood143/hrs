<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Stable;
use Illuminate\Auth\Access\HandlesAuthorization;

class StablePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Stable');
    }

    public function view(AuthUser $authUser, Stable $stable): bool
    {
        return $authUser->can('View:Stable');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Stable');
    }

    public function update(AuthUser $authUser, Stable $stable): bool
    {
        return $authUser->can('Update:Stable');
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
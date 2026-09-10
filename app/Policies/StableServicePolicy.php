<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StableService;
use Illuminate\Auth\Access\HandlesAuthorization;

class StableServicePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StableService');
    }

    public function view(AuthUser $authUser, StableService $stableService): bool
    {
        return $authUser->can('View:StableService');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StableService');
    }

    public function update(AuthUser $authUser, StableService $stableService): bool
    {
        return $authUser->can('Update:StableService');
    }

    public function delete(AuthUser $authUser, StableService $stableService): bool
    {
        return $authUser->can('Delete:StableService');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StableService');
    }

    public function restore(AuthUser $authUser, StableService $stableService): bool
    {
        return $authUser->can('Restore:StableService');
    }

    public function forceDelete(AuthUser $authUser, StableService $stableService): bool
    {
        return $authUser->can('ForceDelete:StableService');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StableService');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StableService');
    }

    public function replicate(AuthUser $authUser, StableService $stableService): bool
    {
        return $authUser->can('Replicate:StableService');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StableService');
    }

}
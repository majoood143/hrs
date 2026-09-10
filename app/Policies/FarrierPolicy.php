<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Farrier;
use Illuminate\Auth\Access\HandlesAuthorization;

class FarrierPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Farrier');
    }

    public function view(AuthUser $authUser, Farrier $farrier): bool
    {
        return $authUser->can('View:Farrier');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Farrier');
    }

    public function update(AuthUser $authUser, Farrier $farrier): bool
    {
        return $authUser->can('Update:Farrier');
    }

    public function delete(AuthUser $authUser, Farrier $farrier): bool
    {
        return $authUser->can('Delete:Farrier');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Farrier');
    }

    public function restore(AuthUser $authUser, Farrier $farrier): bool
    {
        return $authUser->can('Restore:Farrier');
    }

    public function forceDelete(AuthUser $authUser, Farrier $farrier): bool
    {
        return $authUser->can('ForceDelete:Farrier');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Farrier');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Farrier');
    }

    public function replicate(AuthUser $authUser, Farrier $farrier): bool
    {
        return $authUser->can('Replicate:Farrier');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Farrier');
    }

}
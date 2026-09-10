<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Horse;
use Illuminate\Auth\Access\HandlesAuthorization;

class HorsePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Horse');
    }

    public function view(AuthUser $authUser, Horse $horse): bool
    {
        return $authUser->can('View:Horse');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Horse');
    }

    public function update(AuthUser $authUser, Horse $horse): bool
    {
        return $authUser->can('Update:Horse');
    }

    public function delete(AuthUser $authUser, Horse $horse): bool
    {
        return $authUser->can('Delete:Horse');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Horse');
    }

    public function restore(AuthUser $authUser, Horse $horse): bool
    {
        return $authUser->can('Restore:Horse');
    }

    public function forceDelete(AuthUser $authUser, Horse $horse): bool
    {
        return $authUser->can('ForceDelete:Horse');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Horse');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Horse');
    }

    public function replicate(AuthUser $authUser, Horse $horse): bool
    {
        return $authUser->can('Replicate:Horse');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Horse');
    }

}
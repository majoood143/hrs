<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Center;
use Illuminate\Auth\Access\HandlesAuthorization;

class CenterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Center');
    }

    public function view(AuthUser $authUser, Center $center): bool
    {
        return $authUser->can('View:Center');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Center');
    }

    public function update(AuthUser $authUser, Center $center): bool
    {
        return $authUser->can('Update:Center');
    }

    public function delete(AuthUser $authUser, Center $center): bool
    {
        return $authUser->can('Delete:Center');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Center');
    }

    public function restore(AuthUser $authUser, Center $center): bool
    {
        return $authUser->can('Restore:Center');
    }

    public function forceDelete(AuthUser $authUser, Center $center): bool
    {
        return $authUser->can('ForceDelete:Center');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Center');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Center');
    }

    public function replicate(AuthUser $authUser, Center $center): bool
    {
        return $authUser->can('Replicate:Center');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Center');
    }

}

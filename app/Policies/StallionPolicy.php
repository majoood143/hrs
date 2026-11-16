<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Stallion;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StallionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Stallion');
    }

    public function view(AuthUser $authUser, Stallion $stallion): bool
    {
        return $authUser->can('View:Stallion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Stallion');
    }

    public function update(AuthUser $authUser, Stallion $stallion): bool
    {
        return $authUser->can('Update:Stallion');
    }

    public function delete(AuthUser $authUser, Stallion $stallion): bool
    {
        return $authUser->can('Delete:Stallion');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Stallion');
    }

    public function restore(AuthUser $authUser, Stallion $stallion): bool
    {
        return $authUser->can('Restore:Stallion');
    }

    public function forceDelete(AuthUser $authUser, Stallion $stallion): bool
    {
        return $authUser->can('ForceDelete:Stallion');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Stallion');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Stallion');
    }

    public function replicate(AuthUser $authUser, Stallion $stallion): bool
    {
        return $authUser->can('Replicate:Stallion');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Stallion');
    }
}

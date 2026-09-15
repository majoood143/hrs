<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CenterService;
use Illuminate\Auth\Access\HandlesAuthorization;

class CenterServicePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CenterService');
    }

    public function view(AuthUser $authUser, CenterService $centerService): bool
    {
        return $authUser->can('View:CenterService');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CenterService');
    }

    public function update(AuthUser $authUser, CenterService $centerService): bool
    {
        return $authUser->can('Update:CenterService');
    }

    public function delete(AuthUser $authUser, CenterService $centerService): bool
    {
        return $authUser->can('Delete:CenterService');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CenterService');
    }

    public function restore(AuthUser $authUser, CenterService $centerService): bool
    {
        return $authUser->can('Restore:CenterService');
    }

    public function forceDelete(AuthUser $authUser, CenterService $centerService): bool
    {
        return $authUser->can('ForceDelete:CenterService');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CenterService');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CenterService');
    }

    public function replicate(AuthUser $authUser, CenterService $centerService): bool
    {
        return $authUser->can('Replicate:CenterService');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CenterService');
    }

}
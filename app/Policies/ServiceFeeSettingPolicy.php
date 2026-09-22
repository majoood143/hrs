<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ServiceFeeSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceFeeSettingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ServiceFeeSetting');
    }

    public function view(AuthUser $authUser, ServiceFeeSetting $serviceFeeSetting): bool
    {
        return $authUser->can('View:ServiceFeeSetting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ServiceFeeSetting');
    }

    public function update(AuthUser $authUser, ServiceFeeSetting $serviceFeeSetting): bool
    {
        return $authUser->can('Update:ServiceFeeSetting');
    }

    public function delete(AuthUser $authUser, ServiceFeeSetting $serviceFeeSetting): bool
    {
        return $authUser->can('Delete:ServiceFeeSetting');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ServiceFeeSetting');
    }

    public function restore(AuthUser $authUser, ServiceFeeSetting $serviceFeeSetting): bool
    {
        return $authUser->can('Restore:ServiceFeeSetting');
    }

    public function forceDelete(AuthUser $authUser, ServiceFeeSetting $serviceFeeSetting): bool
    {
        return $authUser->can('ForceDelete:ServiceFeeSetting');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ServiceFeeSetting');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ServiceFeeSetting');
    }

    public function replicate(AuthUser $authUser, ServiceFeeSetting $serviceFeeSetting): bool
    {
        return $authUser->can('Replicate:ServiceFeeSetting');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ServiceFeeSetting');
    }

}
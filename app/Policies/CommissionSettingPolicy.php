<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CommissionSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommissionSettingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CommissionSetting');
    }

    public function view(AuthUser $authUser, CommissionSetting $commissionSetting): bool
    {
        return $authUser->can('View:CommissionSetting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CommissionSetting');
    }

    public function update(AuthUser $authUser, CommissionSetting $commissionSetting): bool
    {
        return $authUser->can('Update:CommissionSetting');
    }

    public function delete(AuthUser $authUser, CommissionSetting $commissionSetting): bool
    {
        return $authUser->can('Delete:CommissionSetting');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CommissionSetting');
    }

    public function restore(AuthUser $authUser, CommissionSetting $commissionSetting): bool
    {
        return $authUser->can('Restore:CommissionSetting');
    }

    public function forceDelete(AuthUser $authUser, CommissionSetting $commissionSetting): bool
    {
        return $authUser->can('ForceDelete:CommissionSetting');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CommissionSetting');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CommissionSetting');
    }

    public function replicate(AuthUser $authUser, CommissionSetting $commissionSetting): bool
    {
        return $authUser->can('Replicate:CommissionSetting');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CommissionSetting');
    }

}
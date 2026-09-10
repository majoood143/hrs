<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ClinicService;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClinicServicePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClinicService');
    }

    public function view(AuthUser $authUser, ClinicService $clinicService): bool
    {
        return $authUser->can('View:ClinicService');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClinicService');
    }

    public function update(AuthUser $authUser, ClinicService $clinicService): bool
    {
        return $authUser->can('Update:ClinicService');
    }

    public function delete(AuthUser $authUser, ClinicService $clinicService): bool
    {
        return $authUser->can('Delete:ClinicService');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ClinicService');
    }

    public function restore(AuthUser $authUser, ClinicService $clinicService): bool
    {
        return $authUser->can('Restore:ClinicService');
    }

    public function forceDelete(AuthUser $authUser, ClinicService $clinicService): bool
    {
        return $authUser->can('ForceDelete:ClinicService');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ClinicService');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ClinicService');
    }

    public function replicate(AuthUser $authUser, ClinicService $clinicService): bool
    {
        return $authUser->can('Replicate:ClinicService');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ClinicService');
    }

}
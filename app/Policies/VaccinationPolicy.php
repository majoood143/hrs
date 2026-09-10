<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Vaccination;
use Illuminate\Auth\Access\HandlesAuthorization;

class VaccinationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Vaccination');
    }

    public function view(AuthUser $authUser, Vaccination $vaccination): bool
    {
        return $authUser->can('View:Vaccination');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Vaccination');
    }

    public function update(AuthUser $authUser, Vaccination $vaccination): bool
    {
        return $authUser->can('Update:Vaccination');
    }

    public function delete(AuthUser $authUser, Vaccination $vaccination): bool
    {
        return $authUser->can('Delete:Vaccination');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Vaccination');
    }

    public function restore(AuthUser $authUser, Vaccination $vaccination): bool
    {
        return $authUser->can('Restore:Vaccination');
    }

    public function forceDelete(AuthUser $authUser, Vaccination $vaccination): bool
    {
        return $authUser->can('ForceDelete:Vaccination');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Vaccination');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Vaccination');
    }

    public function replicate(AuthUser $authUser, Vaccination $vaccination): bool
    {
        return $authUser->can('Replicate:Vaccination');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Vaccination');
    }

}
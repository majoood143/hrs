<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Authority;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuthorityPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Authority');
    }

    public function view(AuthUser $authUser, Authority $authority): bool
    {
        return $authUser->can('View:Authority');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Authority');
    }

    public function update(AuthUser $authUser, Authority $authority): bool
    {
        return $authUser->can('Update:Authority');
    }

    public function delete(AuthUser $authUser, Authority $authority): bool
    {
        return $authUser->can('Delete:Authority');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Authority');
    }

    public function restore(AuthUser $authUser, Authority $authority): bool
    {
        return $authUser->can('Restore:Authority');
    }

    public function forceDelete(AuthUser $authUser, Authority $authority): bool
    {
        return $authUser->can('ForceDelete:Authority');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Authority');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Authority');
    }

    public function replicate(AuthUser $authUser, Authority $authority): bool
    {
        return $authUser->can('Replicate:Authority');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Authority');
    }

}
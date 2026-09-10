<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CmsRedirect;
use Illuminate\Auth\Access\HandlesAuthorization;

class CmsRedirectPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CmsRedirect');
    }

    public function view(AuthUser $authUser, CmsRedirect $cmsRedirect): bool
    {
        return $authUser->can('View:CmsRedirect');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CmsRedirect');
    }

    public function update(AuthUser $authUser, CmsRedirect $cmsRedirect): bool
    {
        return $authUser->can('Update:CmsRedirect');
    }

    public function delete(AuthUser $authUser, CmsRedirect $cmsRedirect): bool
    {
        return $authUser->can('Delete:CmsRedirect');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CmsRedirect');
    }

    public function restore(AuthUser $authUser, CmsRedirect $cmsRedirect): bool
    {
        return $authUser->can('Restore:CmsRedirect');
    }

    public function forceDelete(AuthUser $authUser, CmsRedirect $cmsRedirect): bool
    {
        return $authUser->can('ForceDelete:CmsRedirect');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CmsRedirect');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CmsRedirect');
    }

    public function replicate(AuthUser $authUser, CmsRedirect $cmsRedirect): bool
    {
        return $authUser->can('Replicate:CmsRedirect');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CmsRedirect');
    }

}
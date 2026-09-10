<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CmsMenu;
use Illuminate\Auth\Access\HandlesAuthorization;

class CmsMenuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CmsMenu');
    }

    public function view(AuthUser $authUser, CmsMenu $cmsMenu): bool
    {
        return $authUser->can('View:CmsMenu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CmsMenu');
    }

    public function update(AuthUser $authUser, CmsMenu $cmsMenu): bool
    {
        return $authUser->can('Update:CmsMenu');
    }

    public function delete(AuthUser $authUser, CmsMenu $cmsMenu): bool
    {
        return $authUser->can('Delete:CmsMenu');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CmsMenu');
    }

    public function restore(AuthUser $authUser, CmsMenu $cmsMenu): bool
    {
        return $authUser->can('Restore:CmsMenu');
    }

    public function forceDelete(AuthUser $authUser, CmsMenu $cmsMenu): bool
    {
        return $authUser->can('ForceDelete:CmsMenu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CmsMenu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CmsMenu');
    }

    public function replicate(AuthUser $authUser, CmsMenu $cmsMenu): bool
    {
        return $authUser->can('Replicate:CmsMenu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CmsMenu');
    }

}
<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MediaLibrary;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaLibraryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MediaLibrary');
    }

    public function view(AuthUser $authUser, MediaLibrary $mediaLibrary): bool
    {
        return $authUser->can('View:MediaLibrary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MediaLibrary');
    }

    public function update(AuthUser $authUser, MediaLibrary $mediaLibrary): bool
    {
        return $authUser->can('Update:MediaLibrary');
    }

    public function delete(AuthUser $authUser, MediaLibrary $mediaLibrary): bool
    {
        return $authUser->can('Delete:MediaLibrary');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MediaLibrary');
    }

    public function restore(AuthUser $authUser, MediaLibrary $mediaLibrary): bool
    {
        return $authUser->can('Restore:MediaLibrary');
    }

    public function forceDelete(AuthUser $authUser, MediaLibrary $mediaLibrary): bool
    {
        return $authUser->can('ForceDelete:MediaLibrary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MediaLibrary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MediaLibrary');
    }

    public function replicate(AuthUser $authUser, MediaLibrary $mediaLibrary): bool
    {
        return $authUser->can('Replicate:MediaLibrary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MediaLibrary');
    }

}
<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\VideoFolder;
use Illuminate\Auth\Access\HandlesAuthorization;

class VideoFolderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VideoFolder');
    }

    public function view(AuthUser $authUser, VideoFolder $videoFolder): bool
    {
        return $authUser->can('View:VideoFolder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VideoFolder');
    }

    public function update(AuthUser $authUser, VideoFolder $videoFolder): bool
    {
        return $authUser->can('Update:VideoFolder');
    }

    public function delete(AuthUser $authUser, VideoFolder $videoFolder): bool
    {
        return $authUser->can('Delete:VideoFolder');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VideoFolder');
    }

    public function restore(AuthUser $authUser, VideoFolder $videoFolder): bool
    {
        return $authUser->can('Restore:VideoFolder');
    }

    public function forceDelete(AuthUser $authUser, VideoFolder $videoFolder): bool
    {
        return $authUser->can('ForceDelete:VideoFolder');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VideoFolder');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VideoFolder');
    }

    public function replicate(AuthUser $authUser, VideoFolder $videoFolder): bool
    {
        return $authUser->can('Replicate:VideoFolder');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VideoFolder');
    }

}
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\YouTubeChannel;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class YouTubeChannelPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:YouTubeChannel');
    }

    public function view(AuthUser $authUser, YouTubeChannel $youTubeChannel): bool
    {
        return $authUser->can('View:YouTubeChannel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:YouTubeChannel');
    }

    public function update(AuthUser $authUser, YouTubeChannel $youTubeChannel): bool
    {
        return $authUser->can('Update:YouTubeChannel');
    }

    public function delete(AuthUser $authUser, YouTubeChannel $youTubeChannel): bool
    {
        return $authUser->can('Delete:YouTubeChannel');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:YouTubeChannel');
    }

    public function restore(AuthUser $authUser, YouTubeChannel $youTubeChannel): bool
    {
        return $authUser->can('Restore:YouTubeChannel');
    }

    public function forceDelete(AuthUser $authUser, YouTubeChannel $youTubeChannel): bool
    {
        return $authUser->can('ForceDelete:YouTubeChannel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:YouTubeChannel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:YouTubeChannel');
    }

    public function replicate(AuthUser $authUser, YouTubeChannel $youTubeChannel): bool
    {
        return $authUser->can('Replicate:YouTubeChannel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:YouTubeChannel');
    }
}

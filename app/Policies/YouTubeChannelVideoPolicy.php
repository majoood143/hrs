<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\YouTubeChannelVideo;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class YouTubeChannelVideoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:YouTubeChannelVideo');
    }

    public function view(AuthUser $authUser, YouTubeChannelVideo $youTubeChannelVideo): bool
    {
        return $authUser->can('View:YouTubeChannelVideo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:YouTubeChannelVideo');
    }

    public function update(AuthUser $authUser, YouTubeChannelVideo $youTubeChannelVideo): bool
    {
        return $authUser->can('Update:YouTubeChannelVideo');
    }

    public function delete(AuthUser $authUser, YouTubeChannelVideo $youTubeChannelVideo): bool
    {
        return $authUser->can('Delete:YouTubeChannelVideo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:YouTubeChannelVideo');
    }

    public function restore(AuthUser $authUser, YouTubeChannelVideo $youTubeChannelVideo): bool
    {
        return $authUser->can('Restore:YouTubeChannelVideo');
    }

    public function forceDelete(AuthUser $authUser, YouTubeChannelVideo $youTubeChannelVideo): bool
    {
        return $authUser->can('ForceDelete:YouTubeChannelVideo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:YouTubeChannelVideo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:YouTubeChannelVideo');
    }

    public function replicate(AuthUser $authUser, YouTubeChannelVideo $youTubeChannelVideo): bool
    {
        return $authUser->can('Replicate:YouTubeChannelVideo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:YouTubeChannelVideo');
    }
}

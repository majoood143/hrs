<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SuccessStory;
use Illuminate\Auth\Access\HandlesAuthorization;

class SuccessStoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SuccessStory');
    }

    public function view(AuthUser $authUser, SuccessStory $successStory): bool
    {
        return $authUser->can('View:SuccessStory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SuccessStory');
    }

    public function update(AuthUser $authUser, SuccessStory $successStory): bool
    {
        return $authUser->can('Update:SuccessStory');
    }

    public function delete(AuthUser $authUser, SuccessStory $successStory): bool
    {
        return $authUser->can('Delete:SuccessStory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SuccessStory');
    }

    public function restore(AuthUser $authUser, SuccessStory $successStory): bool
    {
        return $authUser->can('Restore:SuccessStory');
    }

    public function forceDelete(AuthUser $authUser, SuccessStory $successStory): bool
    {
        return $authUser->can('ForceDelete:SuccessStory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SuccessStory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SuccessStory');
    }

    public function replicate(AuthUser $authUser, SuccessStory $successStory): bool
    {
        return $authUser->can('Replicate:SuccessStory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SuccessStory');
    }

}
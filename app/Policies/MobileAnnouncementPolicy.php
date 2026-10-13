<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MobileAnnouncement;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MobileAnnouncementPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MobileAnnouncement');
    }

    public function view(AuthUser $authUser, MobileAnnouncement $mobileAnnouncement): bool
    {
        return $authUser->can('View:MobileAnnouncement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MobileAnnouncement');
    }

    public function update(AuthUser $authUser, MobileAnnouncement $mobileAnnouncement): bool
    {
        return $authUser->can('Update:MobileAnnouncement');
    }

    public function delete(AuthUser $authUser, MobileAnnouncement $mobileAnnouncement): bool
    {
        return $authUser->can('Delete:MobileAnnouncement');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MobileAnnouncement');
    }

    public function restore(AuthUser $authUser, MobileAnnouncement $mobileAnnouncement): bool
    {
        return $authUser->can('Restore:MobileAnnouncement');
    }

    public function forceDelete(AuthUser $authUser, MobileAnnouncement $mobileAnnouncement): bool
    {
        return $authUser->can('ForceDelete:MobileAnnouncement');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MobileAnnouncement');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MobileAnnouncement');
    }

    public function replicate(AuthUser $authUser, MobileAnnouncement $mobileAnnouncement): bool
    {
        return $authUser->can('Replicate:MobileAnnouncement');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MobileAnnouncement');
    }
}

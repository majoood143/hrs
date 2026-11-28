<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuctionOrganizer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AuctionOrganizerPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AuctionOrganizer');
    }

    public function view(AuthUser $authUser, AuctionOrganizer $auctionOrganizer): bool
    {
        return $authUser->can('View:AuctionOrganizer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AuctionOrganizer');
    }

    public function update(AuthUser $authUser, AuctionOrganizer $auctionOrganizer): bool
    {
        return $authUser->can('Update:AuctionOrganizer');
    }

    public function delete(AuthUser $authUser, AuctionOrganizer $auctionOrganizer): bool
    {
        return $authUser->can('Delete:AuctionOrganizer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AuctionOrganizer');
    }

    public function restore(AuthUser $authUser, AuctionOrganizer $auctionOrganizer): bool
    {
        return $authUser->can('Restore:AuctionOrganizer');
    }

    public function forceDelete(AuthUser $authUser, AuctionOrganizer $auctionOrganizer): bool
    {
        return $authUser->can('ForceDelete:AuctionOrganizer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AuctionOrganizer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AuctionOrganizer');
    }

    public function replicate(AuthUser $authUser, AuctionOrganizer $auctionOrganizer): bool
    {
        return $authUser->can('Replicate:AuctionOrganizer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AuctionOrganizer');
    }
}

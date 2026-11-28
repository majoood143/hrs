<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuctionLot;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AuctionLotPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AuctionLot');
    }

    public function view(AuthUser $authUser, AuctionLot $auctionLot): bool
    {
        return $authUser->can('View:AuctionLot');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AuctionLot');
    }

    public function update(AuthUser $authUser, AuctionLot $auctionLot): bool
    {
        return $authUser->can('Update:AuctionLot');
    }

    public function delete(AuthUser $authUser, AuctionLot $auctionLot): bool
    {
        return $authUser->can('Delete:AuctionLot');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AuctionLot');
    }

    public function restore(AuthUser $authUser, AuctionLot $auctionLot): bool
    {
        return $authUser->can('Restore:AuctionLot');
    }

    public function forceDelete(AuthUser $authUser, AuctionLot $auctionLot): bool
    {
        return $authUser->can('ForceDelete:AuctionLot');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AuctionLot');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AuctionLot');
    }

    public function replicate(AuthUser $authUser, AuctionLot $auctionLot): bool
    {
        return $authUser->can('Replicate:AuctionLot');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AuctionLot');
    }
}

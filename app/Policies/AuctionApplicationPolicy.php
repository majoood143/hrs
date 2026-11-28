<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuctionApplication;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AuctionApplicationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AuctionApplication');
    }

    public function view(AuthUser $authUser, AuctionApplication $auctionApplication): bool
    {
        return $authUser->can('View:AuctionApplication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AuctionApplication');
    }

    public function update(AuthUser $authUser, AuctionApplication $auctionApplication): bool
    {
        return $authUser->can('Update:AuctionApplication');
    }

    public function delete(AuthUser $authUser, AuctionApplication $auctionApplication): bool
    {
        return $authUser->can('Delete:AuctionApplication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AuctionApplication');
    }

    public function restore(AuthUser $authUser, AuctionApplication $auctionApplication): bool
    {
        return $authUser->can('Restore:AuctionApplication');
    }

    public function forceDelete(AuthUser $authUser, AuctionApplication $auctionApplication): bool
    {
        return $authUser->can('ForceDelete:AuctionApplication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AuctionApplication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AuctionApplication');
    }

    public function replicate(AuthUser $authUser, AuctionApplication $auctionApplication): bool
    {
        return $authUser->can('Replicate:AuctionApplication');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AuctionApplication');
    }
}

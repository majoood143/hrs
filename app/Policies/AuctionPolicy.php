<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Auction;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AuctionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Auction');
    }

    public function view(AuthUser $authUser, Auction $auction): bool
    {
        return $authUser->can('View:Auction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Auction');
    }

    public function update(AuthUser $authUser, Auction $auction): bool
    {
        return $authUser->can('Update:Auction');
    }

    public function delete(AuthUser $authUser, Auction $auction): bool
    {
        return $authUser->can('Delete:Auction');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Auction');
    }

    public function restore(AuthUser $authUser, Auction $auction): bool
    {
        return $authUser->can('Restore:Auction');
    }

    public function forceDelete(AuthUser $authUser, Auction $auction): bool
    {
        return $authUser->can('ForceDelete:Auction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Auction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Auction');
    }

    public function replicate(AuthUser $authUser, Auction $auction): bool
    {
        return $authUser->can('Replicate:Auction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Auction');
    }
}

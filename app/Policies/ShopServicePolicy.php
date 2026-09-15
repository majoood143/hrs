<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ShopService;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShopServicePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ShopService');
    }

    public function view(AuthUser $authUser, ShopService $shopService): bool
    {
        return $authUser->can('View:ShopService');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ShopService');
    }

    public function update(AuthUser $authUser, ShopService $shopService): bool
    {
        return $authUser->can('Update:ShopService');
    }

    public function delete(AuthUser $authUser, ShopService $shopService): bool
    {
        return $authUser->can('Delete:ShopService');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ShopService');
    }

    public function restore(AuthUser $authUser, ShopService $shopService): bool
    {
        return $authUser->can('Restore:ShopService');
    }

    public function forceDelete(AuthUser $authUser, ShopService $shopService): bool
    {
        return $authUser->can('ForceDelete:ShopService');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ShopService');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ShopService');
    }

    public function replicate(AuthUser $authUser, ShopService $shopService): bool
    {
        return $authUser->can('Replicate:ShopService');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ShopService');
    }

}
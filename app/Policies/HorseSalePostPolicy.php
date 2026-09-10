<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HorseSalePost;
use Illuminate\Auth\Access\HandlesAuthorization;

class HorseSalePostPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HorseSalePost');
    }

    public function view(AuthUser $authUser, HorseSalePost $horseSalePost): bool
    {
        return $authUser->can('View:HorseSalePost');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HorseSalePost');
    }

    public function update(AuthUser $authUser, HorseSalePost $horseSalePost): bool
    {
        return $authUser->can('Update:HorseSalePost');
    }

    public function delete(AuthUser $authUser, HorseSalePost $horseSalePost): bool
    {
        return $authUser->can('Delete:HorseSalePost');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:HorseSalePost');
    }

    public function restore(AuthUser $authUser, HorseSalePost $horseSalePost): bool
    {
        return $authUser->can('Restore:HorseSalePost');
    }

    public function forceDelete(AuthUser $authUser, HorseSalePost $horseSalePost): bool
    {
        return $authUser->can('ForceDelete:HorseSalePost');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HorseSalePost');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HorseSalePost');
    }

    public function replicate(AuthUser $authUser, HorseSalePost $horseSalePost): bool
    {
        return $authUser->can('Replicate:HorseSalePost');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HorseSalePost');
    }

}
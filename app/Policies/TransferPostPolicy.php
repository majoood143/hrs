<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TransferPost;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransferPostPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TransferPost');
    }

    public function view(AuthUser $authUser, TransferPost $transferPost): bool
    {
        return $authUser->can('View:TransferPost');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TransferPost');
    }

    public function update(AuthUser $authUser, TransferPost $transferPost): bool
    {
        return $authUser->can('Update:TransferPost');
    }

    public function delete(AuthUser $authUser, TransferPost $transferPost): bool
    {
        return $authUser->can('Delete:TransferPost');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TransferPost');
    }

    public function restore(AuthUser $authUser, TransferPost $transferPost): bool
    {
        return $authUser->can('Restore:TransferPost');
    }

    public function forceDelete(AuthUser $authUser, TransferPost $transferPost): bool
    {
        return $authUser->can('ForceDelete:TransferPost');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TransferPost');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TransferPost');
    }

    public function replicate(AuthUser $authUser, TransferPost $transferPost): bool
    {
        return $authUser->can('Replicate:TransferPost');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TransferPost');
    }

}
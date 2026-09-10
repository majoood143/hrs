<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Attachement;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttachementPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Attachement');
    }

    public function view(AuthUser $authUser, Attachement $attachement): bool
    {
        return $authUser->can('View:Attachement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Attachement');
    }

    public function update(AuthUser $authUser, Attachement $attachement): bool
    {
        return $authUser->can('Update:Attachement');
    }

    public function delete(AuthUser $authUser, Attachement $attachement): bool
    {
        return $authUser->can('Delete:Attachement');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Attachement');
    }

    public function restore(AuthUser $authUser, Attachement $attachement): bool
    {
        return $authUser->can('Restore:Attachement');
    }

    public function forceDelete(AuthUser $authUser, Attachement $attachement): bool
    {
        return $authUser->can('ForceDelete:Attachement');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Attachement');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Attachement');
    }

    public function replicate(AuthUser $authUser, Attachement $attachement): bool
    {
        return $authUser->can('Replicate:Attachement');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Attachement');
    }

}
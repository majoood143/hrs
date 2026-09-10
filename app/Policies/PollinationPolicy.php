<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Pollination;
use Illuminate\Auth\Access\HandlesAuthorization;

class PollinationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Pollination');
    }

    public function view(AuthUser $authUser, Pollination $pollination): bool
    {
        return $authUser->can('View:Pollination');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Pollination');
    }

    public function update(AuthUser $authUser, Pollination $pollination): bool
    {
        return $authUser->can('Update:Pollination');
    }

    public function delete(AuthUser $authUser, Pollination $pollination): bool
    {
        return $authUser->can('Delete:Pollination');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Pollination');
    }

    public function restore(AuthUser $authUser, Pollination $pollination): bool
    {
        return $authUser->can('Restore:Pollination');
    }

    public function forceDelete(AuthUser $authUser, Pollination $pollination): bool
    {
        return $authUser->can('ForceDelete:Pollination');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Pollination');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Pollination');
    }

    public function replicate(AuthUser $authUser, Pollination $pollination): bool
    {
        return $authUser->can('Replicate:Pollination');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Pollination');
    }

}
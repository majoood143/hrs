<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AdZone;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdZonePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AdZone');
    }

    public function view(AuthUser $authUser, AdZone $adZone): bool
    {
        return $authUser->can('View:AdZone');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AdZone');
    }

    public function update(AuthUser $authUser, AdZone $adZone): bool
    {
        return $authUser->can('Update:AdZone');
    }

    public function delete(AuthUser $authUser, AdZone $adZone): bool
    {
        return $authUser->can('Delete:AdZone');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AdZone');
    }

    public function restore(AuthUser $authUser, AdZone $adZone): bool
    {
        return $authUser->can('Restore:AdZone');
    }

    public function forceDelete(AuthUser $authUser, AdZone $adZone): bool
    {
        return $authUser->can('ForceDelete:AdZone');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AdZone');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AdZone');
    }

    public function replicate(AuthUser $authUser, AdZone $adZone): bool
    {
        return $authUser->can('Replicate:AdZone');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AdZone');
    }

}

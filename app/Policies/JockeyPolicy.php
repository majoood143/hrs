<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Jockey;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class JockeyPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Jockey');
    }

    public function view(AuthUser $authUser, Jockey $jockey): bool
    {
        return $authUser->can('View:Jockey');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Jockey');
    }

    public function update(AuthUser $authUser, Jockey $jockey): bool
    {
        return $authUser->can('Update:Jockey');
    }

    public function delete(AuthUser $authUser, Jockey $jockey): bool
    {
        return $authUser->can('Delete:Jockey');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Jockey');
    }

    public function restore(AuthUser $authUser, Jockey $jockey): bool
    {
        return $authUser->can('Restore:Jockey');
    }

    public function forceDelete(AuthUser $authUser, Jockey $jockey): bool
    {
        return $authUser->can('ForceDelete:Jockey');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Jockey');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Jockey');
    }

    public function replicate(AuthUser $authUser, Jockey $jockey): bool
    {
        return $authUser->can('Replicate:Jockey');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Jockey');
    }
}

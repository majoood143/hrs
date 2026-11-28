<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GameAttempt;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GameAttemptPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GameAttempt');
    }

    public function view(AuthUser $authUser, GameAttempt $gameAttempt): bool
    {
        return $authUser->can('View:GameAttempt');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GameAttempt');
    }

    public function update(AuthUser $authUser, GameAttempt $gameAttempt): bool
    {
        return $authUser->can('Update:GameAttempt');
    }

    public function delete(AuthUser $authUser, GameAttempt $gameAttempt): bool
    {
        return $authUser->can('Delete:GameAttempt');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GameAttempt');
    }

    public function restore(AuthUser $authUser, GameAttempt $gameAttempt): bool
    {
        return $authUser->can('Restore:GameAttempt');
    }

    public function forceDelete(AuthUser $authUser, GameAttempt $gameAttempt): bool
    {
        return $authUser->can('ForceDelete:GameAttempt');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GameAttempt');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GameAttempt');
    }

    public function replicate(AuthUser $authUser, GameAttempt $gameAttempt): bool
    {
        return $authUser->can('Replicate:GameAttempt');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GameAttempt');
    }
}

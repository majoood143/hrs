<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StableHorse;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Admins by their Shield permissions; stable owners for their own stable only. */
class StableHorsePolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:StableHorse');
    }

    public function view(AuthUser $user, StableHorse $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:StableHorse');
    }

    public function create(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('Create:StableHorse');
    }

    public function update(AuthUser $user, StableHorse $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:StableHorse');
    }

    public function delete(AuthUser $user, StableHorse $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Delete:StableHorse');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('DeleteAny:StableHorse');
    }
}

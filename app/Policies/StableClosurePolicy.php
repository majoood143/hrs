<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StableClosure;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Admins by their Shield permissions; stable owners for their own stable only. */
class StableClosurePolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:StableClosure');
    }

    public function view(AuthUser $user, StableClosure $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:StableClosure');
    }

    public function create(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('Create:StableClosure');
    }

    public function update(AuthUser $user, StableClosure $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:StableClosure');
    }

    public function delete(AuthUser $user, StableClosure $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Delete:StableClosure');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('DeleteAny:StableClosure');
    }
}

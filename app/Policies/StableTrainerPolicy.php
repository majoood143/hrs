<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StableTrainer;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Admins by their Shield permissions; stable owners for their own stable only. */
class StableTrainerPolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:StableTrainer');
    }

    public function view(AuthUser $user, StableTrainer $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:StableTrainer');
    }

    public function create(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('Create:StableTrainer');
    }

    public function update(AuthUser $user, StableTrainer $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:StableTrainer');
    }

    public function delete(AuthUser $user, StableTrainer $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Delete:StableTrainer');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('DeleteAny:StableTrainer');
    }
}

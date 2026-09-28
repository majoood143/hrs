<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StablePackage;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Admins by their Shield permissions; stable owners for their own stable only. */
class StablePackagePolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:StablePackage');
    }

    public function view(AuthUser $user, StablePackage $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:StablePackage');
    }

    public function create(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('Create:StablePackage');
    }

    public function update(AuthUser $user, StablePackage $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:StablePackage');
    }

    public function delete(AuthUser $user, StablePackage $record): bool
    {
        return ($this->isStableMember($user, $record) && ! $record->purchases()->exists()) || $user->can('Delete:StablePackage');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('DeleteAny:StablePackage');
    }
}

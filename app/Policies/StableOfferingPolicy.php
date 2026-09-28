<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StableOffering;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Admins by their Shield permissions; stable owners for their own stable only. */
class StableOfferingPolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:StableOffering');
    }

    public function view(AuthUser $user, StableOffering $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:StableOffering');
    }

    public function create(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('Create:StableOffering');
    }

    public function update(AuthUser $user, StableOffering $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:StableOffering');
    }

    public function delete(AuthUser $user, StableOffering $record): bool
    {
        return ($this->isStableMember($user, $record) && ! $record->slots()->whereHas('holdingBookings')->exists()) || $user->can('Delete:StableOffering');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('DeleteAny:StableOffering');
    }
}

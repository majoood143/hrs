<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StableBooking;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Admins by their Shield permissions; stable owners for their own stable only. */
class StableBookingPolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:StableBooking');
    }

    public function view(AuthUser $user, StableBooking $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:StableBooking');
    }

    public function create(AuthUser $user): bool
    {
        return false || $user->can('Create:StableBooking');
    }

    public function update(AuthUser $user, StableBooking $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:StableBooking');
    }

    public function delete(AuthUser $user, StableBooking $record): bool
    {
        return false || $user->can('Delete:StableBooking');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('DeleteAny:StableBooking');
    }
}

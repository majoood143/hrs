<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BookingSlot;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Admins by their Shield permissions; stable owners for their own stable only. */
class BookingSlotPolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:BookingSlot');
    }

    public function view(AuthUser $user, BookingSlot $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:BookingSlot');
    }

    public function create(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('Create:BookingSlot');
    }

    public function update(AuthUser $user, BookingSlot $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:BookingSlot');
    }

    public function delete(AuthUser $user, BookingSlot $record): bool
    {
        return ($this->isStableMember($user, $record) && $record->stable_schedule_id === null && ! $record->hasBookings()) || $user->can('Delete:BookingSlot');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('DeleteAny:BookingSlot');
    }
}

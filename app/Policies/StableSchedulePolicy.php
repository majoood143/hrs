<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StableSchedule;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Admins by their Shield permissions; stable owners for their own stable only. */
class StableSchedulePolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:StableSchedule');
    }

    public function view(AuthUser $user, StableSchedule $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:StableSchedule');
    }

    public function create(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('Create:StableSchedule');
    }

    public function update(AuthUser $user, StableSchedule $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:StableSchedule');
    }

    public function delete(AuthUser $user, StableSchedule $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Delete:StableSchedule');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('DeleteAny:StableSchedule');
    }
}

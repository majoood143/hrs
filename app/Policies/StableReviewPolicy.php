<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StableReview;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Admins by their Shield permissions; stable owners for their own stable only. */
class StableReviewPolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:StableReview');
    }

    public function view(AuthUser $user, StableReview $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:StableReview');
    }

    public function create(AuthUser $user): bool
    {
        return false || $user->can('Create:StableReview');
    }

    public function update(AuthUser $user, StableReview $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('Update:StableReview');
    }

    public function delete(AuthUser $user, StableReview $record): bool
    {
        return false || $user->can('Delete:StableReview');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('DeleteAny:StableReview');
    }
}

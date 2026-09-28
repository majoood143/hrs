<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StablePackagePurchase;
use App\Policies\Concerns\ChecksStableMembership;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Admins by their Shield permissions; stable owners for their own stable only. */
class StablePackagePurchasePolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->isStableMember($user) || $user->can('ViewAny:StablePackagePurchase');
    }

    public function view(AuthUser $user, StablePackagePurchase $record): bool
    {
        return $this->isStableMember($user, $record) || $user->can('View:StablePackagePurchase');
    }

    public function create(AuthUser $user): bool
    {
        return false || $user->can('Create:StablePackagePurchase');
    }

    public function update(AuthUser $user, StablePackagePurchase $record): bool
    {
        return false || $user->can('Update:StablePackagePurchase');
    }

    public function delete(AuthUser $user, StablePackagePurchase $record): bool
    {
        return false || $user->can('Delete:StablePackagePurchase');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('DeleteAny:StablePackagePurchase');
    }
}

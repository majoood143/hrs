<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Policies\Concerns\ChecksStableMembership;
use App\Services\Reports\ClosedBooks;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Nothing while the Expenses module is off. Stable owners: their own stable's expenses. Admins by
 * their Shield permissions: they change only ours (a stable's are read-only in /admin) and none
 * dated in a closed month.
 */
class ExpensePolicy
{
    use ChecksStableMembership;
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return Expense::enabled() && ($this->isStableMember($user) || $user->can('ViewAny:Expense'));
    }

    public function view(AuthUser $user, Expense $record): bool
    {
        return Expense::enabled() && ($this->isStableMember($user, $record) || $user->can('View:Expense'));
    }

    public function create(AuthUser $user): bool
    {
        return Expense::enabled() && ($this->isStableMember($user) || $user->can('Create:Expense'));
    }

    public function update(AuthUser $user, Expense $record): bool
    {
        return Expense::enabled() && ($this->isStableMember($user, $record) || $this->adminMayChange($user, $record, 'Update:Expense'));
    }

    public function delete(AuthUser $user, Expense $record): bool
    {
        return Expense::enabled() && ($this->isStableMember($user, $record) || $this->adminMayChange($user, $record, 'Delete:Expense'));
    }

    public function deleteAny(AuthUser $user): bool
    {
        return false;
    }

    private function adminMayChange(AuthUser $user, Expense $record, string $permission): bool
    {
        return $record->stable_id === null
            && ! ClosedBooks::isClosed($record->spent_on?->toDateString())
            && $user->can($permission);
    }
}

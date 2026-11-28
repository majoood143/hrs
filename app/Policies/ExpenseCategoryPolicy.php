<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ExpenseCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return Expense::enabled() && $user->can('ViewAny:ExpenseCategory');
    }

    public function view(AuthUser $user, ExpenseCategory $record): bool
    {
        return Expense::enabled() && $user->can('View:ExpenseCategory');
    }

    public function create(AuthUser $user): bool
    {
        return Expense::enabled() && $user->can('Create:ExpenseCategory');
    }

    public function update(AuthUser $user, ExpenseCategory $record): bool
    {
        return Expense::enabled() && $user->can('Update:ExpenseCategory');
    }

    /** One in use cannot go (switch it off instead). */
    public function delete(AuthUser $user, ExpenseCategory $record): bool
    {
        return Expense::enabled() && $user->can('Delete:ExpenseCategory') && ! $record->expenses()->withTrashed()->exists();
    }

    public function deleteAny(AuthUser $user): bool
    {
        return false;
    }
}

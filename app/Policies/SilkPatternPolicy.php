<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SilkPattern;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SilkPatternPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SilkPattern');
    }

    public function view(AuthUser $authUser, SilkPattern $silkPattern): bool
    {
        return $authUser->can('View:SilkPattern');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SilkPattern');
    }

    public function update(AuthUser $authUser, SilkPattern $silkPattern): bool
    {
        return $authUser->can('Update:SilkPattern');
    }

    public function delete(AuthUser $authUser, SilkPattern $silkPattern): bool
    {
        return $authUser->can('Delete:SilkPattern');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SilkPattern');
    }

    public function restore(AuthUser $authUser, SilkPattern $silkPattern): bool
    {
        return $authUser->can('Restore:SilkPattern');
    }

    public function forceDelete(AuthUser $authUser, SilkPattern $silkPattern): bool
    {
        return $authUser->can('ForceDelete:SilkPattern');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SilkPattern');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SilkPattern');
    }

    public function replicate(AuthUser $authUser, SilkPattern $silkPattern): bool
    {
        return $authUser->can('Replicate:SilkPattern');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SilkPattern');
    }
}

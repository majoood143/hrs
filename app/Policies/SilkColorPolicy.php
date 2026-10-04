<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SilkColor;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SilkColorPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SilkColor');
    }

    public function view(AuthUser $authUser, SilkColor $silkColor): bool
    {
        return $authUser->can('View:SilkColor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SilkColor');
    }

    public function update(AuthUser $authUser, SilkColor $silkColor): bool
    {
        return $authUser->can('Update:SilkColor');
    }

    public function delete(AuthUser $authUser, SilkColor $silkColor): bool
    {
        return $authUser->can('Delete:SilkColor');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SilkColor');
    }

    public function restore(AuthUser $authUser, SilkColor $silkColor): bool
    {
        return $authUser->can('Restore:SilkColor');
    }

    public function forceDelete(AuthUser $authUser, SilkColor $silkColor): bool
    {
        return $authUser->can('ForceDelete:SilkColor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SilkColor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SilkColor');
    }

    public function replicate(AuthUser $authUser, SilkColor $silkColor): bool
    {
        return $authUser->can('Replicate:SilkColor');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SilkColor');
    }
}

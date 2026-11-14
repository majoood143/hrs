<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CrmSegment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CrmSegmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CrmSegment');
    }

    public function view(AuthUser $authUser, CrmSegment $crmSegment): bool
    {
        return $authUser->can('View:CrmSegment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CrmSegment');
    }

    public function update(AuthUser $authUser, CrmSegment $crmSegment): bool
    {
        return $authUser->can('Update:CrmSegment');
    }

    public function delete(AuthUser $authUser, CrmSegment $crmSegment): bool
    {
        return $authUser->can('Delete:CrmSegment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CrmSegment');
    }

    public function restore(AuthUser $authUser, CrmSegment $crmSegment): bool
    {
        return $authUser->can('Restore:CrmSegment');
    }

    public function forceDelete(AuthUser $authUser, CrmSegment $crmSegment): bool
    {
        return $authUser->can('ForceDelete:CrmSegment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CrmSegment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CrmSegment');
    }

    public function replicate(AuthUser $authUser, CrmSegment $crmSegment): bool
    {
        return $authUser->can('Replicate:CrmSegment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CrmSegment');
    }
}

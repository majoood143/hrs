<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PushCampaign;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PushCampaignPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PushCampaign');
    }

    public function view(AuthUser $authUser, PushCampaign $pushCampaign): bool
    {
        return $authUser->can('View:PushCampaign');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PushCampaign');
    }

    public function update(AuthUser $authUser, PushCampaign $pushCampaign): bool
    {
        // once it is going out, it cannot change
        return $authUser->can('Update:PushCampaign') && $pushCampaign->editable();
    }

    public function delete(AuthUser $authUser, PushCampaign $pushCampaign): bool
    {
        return $authUser->can('Delete:PushCampaign');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PushCampaign');
    }

    public function restore(AuthUser $authUser, PushCampaign $pushCampaign): bool
    {
        return $authUser->can('Restore:PushCampaign');
    }

    public function forceDelete(AuthUser $authUser, PushCampaign $pushCampaign): bool
    {
        return $authUser->can('ForceDelete:PushCampaign');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PushCampaign');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PushCampaign');
    }

    public function replicate(AuthUser $authUser, PushCampaign $pushCampaign): bool
    {
        return $authUser->can('Replicate:PushCampaign');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PushCampaign');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GameCampaign;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GameCampaignPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GameCampaign');
    }

    public function view(AuthUser $authUser, GameCampaign $gameCampaign): bool
    {
        return $authUser->can('View:GameCampaign');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GameCampaign');
    }

    public function update(AuthUser $authUser, GameCampaign $gameCampaign): bool
    {
        return $authUser->can('Update:GameCampaign');
    }

    public function delete(AuthUser $authUser, GameCampaign $gameCampaign): bool
    {
        return $authUser->can('Delete:GameCampaign');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GameCampaign');
    }

    public function restore(AuthUser $authUser, GameCampaign $gameCampaign): bool
    {
        return $authUser->can('Restore:GameCampaign');
    }

    public function forceDelete(AuthUser $authUser, GameCampaign $gameCampaign): bool
    {
        return $authUser->can('ForceDelete:GameCampaign');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GameCampaign');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GameCampaign');
    }

    public function replicate(AuthUser $authUser, GameCampaign $gameCampaign): bool
    {
        return $authUser->can('Replicate:GameCampaign');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GameCampaign');
    }
}

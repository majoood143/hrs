<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ToolSalePost;
use Illuminate\Auth\Access\HandlesAuthorization;

class ToolSalePostPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ToolSalePost');
    }

    public function view(AuthUser $authUser, ToolSalePost $toolSalePost): bool
    {
        return $authUser->can('View:ToolSalePost');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ToolSalePost');
    }

    public function update(AuthUser $authUser, ToolSalePost $toolSalePost): bool
    {
        return $authUser->can('Update:ToolSalePost');
    }

    public function delete(AuthUser $authUser, ToolSalePost $toolSalePost): bool
    {
        return $authUser->can('Delete:ToolSalePost');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ToolSalePost');
    }

    public function restore(AuthUser $authUser, ToolSalePost $toolSalePost): bool
    {
        return $authUser->can('Restore:ToolSalePost');
    }

    public function forceDelete(AuthUser $authUser, ToolSalePost $toolSalePost): bool
    {
        return $authUser->can('ForceDelete:ToolSalePost');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ToolSalePost');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ToolSalePost');
    }

    public function replicate(AuthUser $authUser, ToolSalePost $toolSalePost): bool
    {
        return $authUser->can('Replicate:ToolSalePost');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ToolSalePost');
    }

}
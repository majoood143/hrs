<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContentReport;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ContentReportPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContentReport');
    }

    public function view(AuthUser $authUser, ContentReport $contentReport): bool
    {
        return $authUser->can('View:ContentReport');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, ContentReport $contentReport): bool
    {
        return $authUser->can('Update:ContentReport');
    }

    public function delete(AuthUser $authUser, ContentReport $contentReport): bool
    {
        return $authUser->can('Delete:ContentReport');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ContentReport');
    }
}

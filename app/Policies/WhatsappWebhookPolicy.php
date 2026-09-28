<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use WallaceMartinss\FilamentEvolution\Models\WhatsappWebhook;

class WhatsappWebhookPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsappWebhook');
    }

    public function view(AuthUser $authUser, WhatsappWebhook $whatsappWebhook): bool
    {
        return $authUser->can('View:WhatsappWebhook');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsappWebhook');
    }

    public function update(AuthUser $authUser, WhatsappWebhook $whatsappWebhook): bool
    {
        return $authUser->can('Update:WhatsappWebhook');
    }

    public function delete(AuthUser $authUser, WhatsappWebhook $whatsappWebhook): bool
    {
        return $authUser->can('Delete:WhatsappWebhook');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WhatsappWebhook');
    }

    public function restore(AuthUser $authUser, WhatsappWebhook $whatsappWebhook): bool
    {
        return $authUser->can('Restore:WhatsappWebhook');
    }

    public function forceDelete(AuthUser $authUser, WhatsappWebhook $whatsappWebhook): bool
    {
        return $authUser->can('ForceDelete:WhatsappWebhook');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsappWebhook');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsappWebhook');
    }

    public function replicate(AuthUser $authUser, WhatsappWebhook $whatsappWebhook): bool
    {
        return $authUser->can('Replicate:WhatsappWebhook');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsappWebhook');
    }
}

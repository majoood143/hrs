<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PaymentGatewayLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentGatewayLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PaymentGatewayLog');
    }

    public function view(AuthUser $authUser, PaymentGatewayLog $paymentGatewayLog): bool
    {
        return $authUser->can('View:PaymentGatewayLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PaymentGatewayLog');
    }

    public function update(AuthUser $authUser, PaymentGatewayLog $paymentGatewayLog): bool
    {
        return $authUser->can('Update:PaymentGatewayLog');
    }

    public function delete(AuthUser $authUser, PaymentGatewayLog $paymentGatewayLog): bool
    {
        return $authUser->can('Delete:PaymentGatewayLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PaymentGatewayLog');
    }

    public function restore(AuthUser $authUser, PaymentGatewayLog $paymentGatewayLog): bool
    {
        return $authUser->can('Restore:PaymentGatewayLog');
    }

    public function forceDelete(AuthUser $authUser, PaymentGatewayLog $paymentGatewayLog): bool
    {
        return $authUser->can('ForceDelete:PaymentGatewayLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PaymentGatewayLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PaymentGatewayLog');
    }

    public function replicate(AuthUser $authUser, PaymentGatewayLog $paymentGatewayLog): bool
    {
        return $authUser->can('Replicate:PaymentGatewayLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PaymentGatewayLog');
    }

}
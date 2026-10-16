<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\V1\Owner\OwnerAuthController;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner endpoints (/api/v1/owner/*), after `auth:owner_api`: only a stable owner's own app token
 * (ability `stable_owner`, from OwnerAuthController). An admin's token is a User token too, and is
 * refused here: admins work on stables in the website's panel, where their visits are logged.
 */
class EnsureStableOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('owner_api');
        $token = $user?->currentAccessToken();

        if (! $user instanceof User || ! $user->isStableOwner() || ! $token instanceof PersonalAccessToken
            || ! in_array(OwnerAuthController::ABILITY, (array) $token->abilities, true)) {
            return response()->json(['message' => __('api.owner.not_owner'), 'code' => 'not_stable_owner'], 403);
        }

        return $next($request);
    }
}

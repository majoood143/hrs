<?php

namespace App\Http\Controllers\Api\V1\Owner\Concerns;

use App\Models\Stable;
use App\Models\User;
use Illuminate\Http\Request;

/** The stable in the URL, among the signed-in owner's own: someone else's is simply not found. */
trait FindsOwnerStable
{
    protected function ownerStable(Request $request, string $slug): Stable
    {
        /** @var User $user */
        $user = $request->user('owner_api');

        return $user->stables()->where('stables.slug', $slug)->firstOrFail();
    }
}

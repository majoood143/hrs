<?php

namespace App\Http\Middleware;

use App\Models\Stable;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notes in a stable's change log when an admin opens its panel (once per admin session and
 * stable), so the log shows who looked in on the owner's behalf even when nothing was changed.
 */
class LogAdminStableAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $stable = Filament::getTenant();

        if ($user instanceof User && $stable instanceof Stable && ! $user->belongsToStable($stable->getKey()) && $user->managesStables()) {
            $key = 'stable_admin_access.'.$stable->getKey();

            if (! $request->session()->has($key)) {
                activity('stable')
                    ->performedOn($stable)
                    ->causedBy($user)
                    ->event('admin_opened')
                    ->withProperties(['stable_id' => $stable->getKey(), 'as_admin' => true])
                    ->log('opened the stable panel');

                $request->session()->put($key, now()->toIso8601String());
            }
        }

        return $next($request);
    }
}

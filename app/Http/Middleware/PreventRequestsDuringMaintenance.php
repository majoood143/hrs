<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be reachable even while the app is down, so staff
     * can always sign in and turn maintenance mode back off.
     *
     * @var array<int, string>
     */
    protected $except = [
        'admin',
        'admin/*',
        'livewire/*',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        if ($this->app->maintenanceMode()->active()) {
            $data = $this->app->maintenanceMode()->data();

            $allowed = (array) ($data['allowed'] ?? []);

            if ($allowed && $request->ip() && in_array($request->ip(), $allowed, true)) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }
}

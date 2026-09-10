<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('languages.available', ['en' => []]));
        $default = config('languages.default', 'en');

        $locale = $request->query('lang');

        if (is_string($locale) && in_array($locale, $available, true)) {
            session(['locale' => $locale]);
        } else {
            $locale = session('locale', $default);

            if (! in_array($locale, $available, true)) {
                $locale = $default;
            }
        }

        App::setLocale($locale);

        return $next($request);
    }
}

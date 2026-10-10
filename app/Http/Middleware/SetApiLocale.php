<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mobile API's counterpart of SetLocale: no session and no ?lang=, the language comes from the
 * Accept-Language header (the first of `config('languages.available')` it names, "ar-OM" counting
 * as "ar"), else the site's default. Every text in the response is then already in that language.
 */
class SetApiLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($locale = self::resolve($request));

        $response = $next($request);

        $response->headers->set('Content-Language', $locale);
        $response->setVary('Accept-Language', false);

        return $response;
    }

    public static function resolve(Request $request): string
    {
        $available = array_keys(config('languages.available', ['en' => []]));
        $default = config('languages.default', 'en');

        if ($request->headers->has('Accept-Language')) {
            foreach ($request->getLanguages() as $language) {
                $code = strtolower(strtok($language, '_-'));

                if (in_array($code, $available, true)) {
                    return $code;
                }
            }
        }

        return in_array($default, $available, true) ? $default : $available[0];
    }
}

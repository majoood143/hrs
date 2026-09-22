<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\App;

class Locale
{
    /** Run something in another language and put the current one back afterwards, whatever happens. */
    public static function within(string $locale, Closure $callback): mixed
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return $callback();
        } finally {
            App::setLocale($previous);
        }
    }
}

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Available Languages
    |--------------------------------------------------------------------------
    |
    | The full list of locales the public website can be displayed in. To add
    | a new language in the future, add an entry here (and matching entries
    | in lang/{code}/*.php, lang/{code}.json, and any translatable database
    | fields) — the language switcher, the locale middleware, and the CMS
    | forms that use App\Filament\Support\TranslatableInput all read from
    | this list automatically.
    |
    */

    'available' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'dir' => 'ltr',
            'flag' => '🇬🇧',
        ],
        'ar' => [
            'name' => 'Arabic',
            'native' => 'العربية',
            'dir' => 'rtl',
            'flag' => '🇴🇲',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    |
    | Used when a visitor has no locale in their session and no ?lang= query
    | parameter is present, and as the fallback when a translation is missing
    | in the visitor's chosen language.
    |
    */

    'default' => env('APP_LOCALE', 'en'),

];

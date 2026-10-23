<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Racing Data Source
    |--------------------------------------------------------------------------
    |
    | Base URL of the racing database (horse / owner / jockey / trainer search
    | and detail pages): the racing club's iframe site. It is only ever called
    | from the server (App\Services\Racing\RacingClient), never from the browser
    | or the app: we parse and cache its pages and re-present them.
    |
    */

    'base_url' => env('RACING_BASE_URL', 'https://iframe.rhrc.om'),

    // Public website of the data's owner, credited on every racing page and PDF
    // (the base_url above is only the address we fetch from, never shown).
    'source_url' => env('RACING_SOURCE_URL', 'https://rhrc.om'),

    'timeout' => (int) env('RACING_TIMEOUT', 15),

    // The source's race times are the course's local time (race prediction forms close at the first race).
    'timezone' => env('RACING_TIMEZONE', 'Asia/Muscat'),

    // Seconds parsed search results / profiles are cached for.
    'cache_ttl' => (int) env('RACING_CACHE_TTL', 600),

    // The source has no result cap ("al" matches 1,000+ horses), so we require
    // a minimum query length and paginate on our side.
    'min_query_length' => 3,

    'max_query_length' => 60,

    'per_page' => 25,

    // The handicap grid is one big, slow call (all breeds/locations ~10s, ~4k horses), so a
    // filter's full list is cached this long and searched / sorted / paginated on our side.
    'handicap_ttl' => (int) env('RACING_HANDICAP_TTL', 3600),

    'handicap_timeout' => (int) env('RACING_HANDICAP_TIMEOUT', 45),

    'handicap_per_page' => 50,

    // Race days / calendar links barely change (a season is fixed months ahead), so cache them longer.
    'calendar_ttl' => (int) env('RACING_CALENDAR_TTL', 3600),

    // Once a race day is over its races, results and links no longer change, so they are cached for a day.
    'past_day_ttl' => (int) env('RACING_PAST_DAY_TTL', 86400),

    // Minutes the automatic race-result WhatsApp waits for the YouTube replay once the results are out
    // (racing:result-alerts); a replay that comes later still goes out as a short follow-up.
    'result_alert_video_wait' => (int) env('RACING_RESULT_ALERT_VIDEO_WAIT', 20),

    // Rows shown per section on the "All" search before linking to the full list.
    'all_preview' => 10,

];

<?php

return [
    /*
    | Slot dates and times are wall-clock times at the stable. The app itself runs in UTC, so
    | "has this slot started / passed its cutoff" is always worked out in this zone.
    */
    'timezone' => env('STABLE_BOOKINGS_TIMEZONE', 'Asia/Muscat'),

    /* How far ahead the weekly schedules are turned into slots, when a stable has not set its own. */
    'default_horizon_days' => 30,

    /* The most a stable may open ahead (its own setting is capped at this). */
    'max_horizon_days' => 120,
];

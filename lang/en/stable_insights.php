<?php

// Insights on stable bookings: the owner's Insights page, the admins' Stable insights page and the
// admin dashboard widget. Keep lang/ar/stable_insights.php in step.
return [
    'title' => 'Insights',
    'admin_title' => 'Stable insights',
    'all_stables' => 'All stables',
    'export' => 'Stables ranking (CSV)',

    'kpi' => [
        'sales' => 'Sales',
        'stable_share' => 'Your share',
        'our_share' => 'Our earnings',
        'commission' => 'Commission',
        'bookings' => 'Bookings',
        'occupancy' => 'Places sold',
        'occupancy_hint' => 'Of the places open in sessions held',
        'customers' => 'Customers',
        'customers_hint' => ':new new · :returning returning',
        'cancellations' => 'Cancelled',
        'no_show' => 'No-shows: :rate',
        'rating' => 'Rating',
        'vs_previous' => ':delta vs the previous period',
        'no_compare' => 'Nothing to compare with',
    ],

    'chart' => [
        'heading_day' => 'Sales by day',
        'heading_week' => 'Sales by week',
        'heading_month' => 'Sales by month',
        'description_owner' => 'What customers paid for the stable, split into your share and the commission (the service fee is not included).',
        'description_admin' => 'The stables\' share and our earnings (service fees and commission).',
        'stable_share' => 'Your share',
        'commission' => 'Commission',
        'stables_share' => 'Stables\' share',
        'our_share' => 'Our earnings',
    ],

    'occupancy' => [
        'heading' => 'When places sell',
        'description' => 'Places sold out of places open, by day and start time, in sessions already held.',
        'day' => 'Day',
        'cell' => ':day :time: :booked of :capacity places (:pct%)',
        'overall' => 'Overall :pct% (:booked of :capacity places)',
        'empty' => 'No sessions held in this period yet.',
    ],

    'services' => [
        'heading' => 'What sells',
        'name' => 'Service or package',
        'sold' => 'Sold',
        'empty' => 'Nothing sold in this period.',
    ],

    'stables' => [
        'heading' => 'Stables',
        'description' => 'Most sold first. Our earnings are the service fees and commission.',
    ],

    'dashboard' => [
        'heading' => 'Stable bookings this month',
        'sales' => 'Sales :amount',
        'waiting' => 'Waiting for an admin',
        'waiting_hint' => ':stables stables to approve · :keys gateway keys to review',
    ],
];

<?php

return [
    'title' => 'Income & Commission Report',

    'filters' => [
        'period' => 'Period',
        'date_from' => 'From',
        'date_to' => 'To',
        'service' => 'Service',
        'all_services' => 'All services',
        'gateway' => 'Payment gateway',
        'all_gateways' => 'All gateways',
        'language' => 'Download language',
        'language_helper' => 'For the PDF and CSV.',
    ],

    'periods' => [
        'today' => 'Today',
        'this_week' => 'This week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'custom' => 'Custom range',
    ],

    'cards' => [
        'net_to_us' => 'Ours after gateway charges',
        'net_hint' => 'Less :cost estimated gateway charges',
        'due_from_stables' => 'Earned on bookings stables collected',
        'due_from_stables_hint' => 'Our fees and commission, owed by the stables (see Stable balances)',
        'collected' => 'Collected from customers',
        'collected_hint' => ':count paid order(s), all into the client\'s account',
        'due_to_us' => 'Amount due to us',
        'due_hint' => 'Fees :fee (with VAT) + commission :commission',
        'client_keeps' => 'Remains with the client',
        'client_hint' => 'Collected, less refunds and what is due to us',
        'refunded' => 'Refunded to customers',
        'refunded_hint' => 'Fees are refunded only when we or the provider cancelled',
    ],

    'sections' => [
        'breakdown' => 'Breakdown',
        'by_gateway' => 'By payment gateway',
    ],

    'actions' => [
        'pdf' => 'Download PDF statement',
        'csv' => 'Download CSV',
    ],

    'empty' => 'No paid orders in this period.',

    'widgets' => [
        'heading' => 'Income this month',
        'collected' => 'Collected',
        'orders' => ':count paid order(s) · today :today',
        'due_to_us' => 'Due to us',
        'due_hint' => 'Fees + VAT :fee · commission :commission',
        'client_keeps' => 'Remains with the client',
        'client_hint' => 'After refunds and our share',
        'refunded' => 'Refunded',
        'refunded_hint' => 'Fees kept unless we or the provider cancelled',
        'chart_heading' => 'Daily income',
        'last_days' => 'Last :days days',
    ],
];

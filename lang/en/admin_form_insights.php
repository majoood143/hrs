<?php

return [
    'action' => 'Insights',
    'title' => 'Insights: :form',
    'breadcrumb' => 'Insights',

    'filters' => [
        'period' => 'Period',
        'date_from' => 'From',
        'date_to' => 'To',
        'order_status' => 'Order status',
        'all_statuses' => 'All statuses',
        'order_status_helper' => 'Only submissions whose order has this status.',
        'language' => 'Download language',
        'language_helper' => 'The language of the PDF and CSV.',
    ],

    'periods' => [
        'all' => 'All time',
        'today' => 'Today',
        'this_week' => 'This week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'custom' => 'Custom dates',
    ],

    'cards' => [
        'submissions' => 'Submissions',
        'vs_previous' => ':delta compared with the period before (:previous)',
        'all_time' => 'Since the first submission',
        'unread' => 'Unread',
        'unread_hint' => 'Submissions in this period nobody has opened yet.',
        'last_submission' => 'Last submission',
        'never' => 'None yet',
    ],

    'sections' => [
        'over_time' => 'Submissions over time',
        'over_time_by_day' => 'Per day',
        'over_time_by_week' => 'Per week',
        'over_time_by_month' => 'Per month',
        'orders_by_status' => 'Orders by status',
        'orders_by_status_hint' => 'Orders created from this form in the period.',
        'money' => 'Money',
        'money_hint' => 'Orders of this form paid in the period, by the date paid (the same figures as the Income report).',
    ],

    'money' => [
        'due_hint' => 'Fee + VAT :fee, commission :commission',
        'open_report' => 'Open the Income report',
    ],

    'kinds' => [
        'choices' => 'one answer',
        'multi' => 'several answers allowed',
        'boolean' => 'tick box',
        'nationality' => 'nationality',
        'number' => 'number',
        'date' => 'date',
    ],

    'field' => [
        'answered' => ':answered of :total answered (:type)',
        'no_answers' => 'Nobody answered this field in this period.',
    ],

    'answers' => [
        'ticked' => 'Ticked',
        'not_ticked' => 'Not ticked',
    ],

    'stats' => [
        'min' => 'Lowest',
        'mean' => 'Average',
        'median' => 'Median',
        'max' => 'Highest',
    ],

    'empty' => 'No submissions in this period.',
    'no_chartable' => 'This form has no fields with set answers to chart (choices, tick boxes, nationality, numbers, dates). Free text, emails, phones and files are never charted.',

    'actions' => [
        'pdf' => 'PDF',
        'csv' => 'CSV',
        'edit_form' => 'Edit form',
    ],

    'export' => [
        'title' => 'Form insights',
        'period' => 'Period',
        'previous_period' => 'Period before',
    ],
];

<?php

return [
    'navigation' => [
        'label' => 'Redirect',
        'plural' => 'Redirects',
    ],
    'sections' => [
        'information' => 'Redirect Information',
    ],
    'fields' => [
        'from_path' => 'From Path',
        'from_path_helper' => 'The old path, without a leading slash, e.g. "old-page".',
        'to_path' => 'To Path',
        'status_code' => 'Status Code',
    ],
    'status_codes' => [
        '301' => '301 — Permanent',
        '302' => '302 — Temporary',
    ],
    'columns' => [
        'from_path' => 'From',
        'to_path' => 'To',
        'status_code' => 'Status',
        'hits' => 'Hits',
    ],
    'actions' => [
        'create_first' => 'Create your first redirect',
    ],
    'empty_state' => [
        'heading' => 'No redirects yet',
        'description' => 'Redirect old or broken links to a new destination.',
    ],
];

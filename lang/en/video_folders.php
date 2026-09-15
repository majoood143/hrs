<?php

return [
    'navigation' => [
        'label' => 'Video Folder',
        'plural' => 'Video Folders',
        'videos' => 'Videos',
    ],
    'fields' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'order' => 'Order',
        'order_helper' => 'Lower numbers appear first. Newly created folders sort before older ones by default.',
        'is_active' => 'Active',
    ],
    'empty_state' => [
        'heading' => 'No video folders yet',
        'description' => 'Create a folder (e.g. a season) to start adding videos to it.',
    ],
];

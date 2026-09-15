<?php

return [
    'navigation' => [
        'label' => 'Video Folder',
        'plural' => 'Video Folders',
        'videos' => 'Videos',
        'subfolders' => 'Subfolders',
    ],
    'fields' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'parent' => 'Parent Folder',
        'parent_helper' => 'Optional — nest this folder inside another one (e.g. "Matches" inside "2026/2027 Season"). Leave empty for a top-level folder.',
        'order' => 'Order',
        'order_helper' => 'Lower numbers appear first. Newly created folders sort before older ones by default.',
        'is_active' => 'Active',
    ],
    'empty_state' => [
        'heading' => 'No video folders yet',
        'description' => 'Create a folder (e.g. a season) to start adding videos to it.',
    ],
];

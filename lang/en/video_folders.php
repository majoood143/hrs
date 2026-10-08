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
        'card_image' => 'Card Image',
        'card_image_helper' => 'Optional — shown on the folder card in the video library. Falls back to a default icon when empty.',
        'date' => 'Date',
        'date_helper' => 'Optional — e.g. the event or season date. Shown on the folder card when set.',
        'parent' => 'Parent Folder',
        'parent_helper' => 'Optional — nest this folder inside another one (e.g. "Matches" inside "2026/2027 Season"). Leave empty for a top-level folder.',
        'order' => 'Order',
        'order_helper' => 'Lower numbers appear first. Newly created folders sort before older ones by default.',
        'is_active' => 'Active',
    ],
    'filters' => [
        'parent' => 'Parent folder',
        'level' => 'Level',
        'level_all' => 'All folders',
        'level_top' => 'Top-level only',
        'level_sub' => 'Subfolders only',
        'has_videos' => 'Has videos',
        'date_from' => 'Date from',
        'date_until' => 'Date until',
    ],
    'empty_state' => [
        'heading' => 'No video folders yet',
        'description' => 'Create a folder (e.g. a season) to start adding videos to it.',
    ],
];

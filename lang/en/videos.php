<?php

return [
    'navigation' => [
        'label' => 'Video',
        'plural' => 'Videos',
    ],
    'fields' => [
        'folder' => 'Folder',
        'title' => 'Title',
        'slug' => 'Slug',
        'description' => 'Description',
        'youtube_url' => 'YouTube URL',
        'youtube_url_helper' => 'Paste a YouTube link (e.g. youtube.com/watch?v=... or youtu.be/...).',
        'order' => 'Order',
        'is_active' => 'Active',
    ],
    'filters' => [
        'folder' => 'Folder',
    ],
    'validation' => [
        'youtube_url' => 'Enter a valid YouTube video URL.',
    ],
    'empty_state' => [
        'heading' => 'No videos yet',
        'description' => 'Create a video folder first, then add videos to it.',
    ],
    'library' => [
        'eyebrow' => 'Video Library',
        'title' => 'Video Library',
        'subtitle' => 'Watch highlights, replays, and behind-the-scenes footage from every season.',
        'no_results_title' => 'No videos yet',
        'no_results_body' => 'Check back soon for new videos.',
        'view_all' => 'View all videos',
    ],
    'back_to_library' => 'Back to video library',
];

<?php

return [
    'nav_label' => 'Events',
    'navigation' => [
        'label' => 'Event',
        'plural' => 'Events',
    ],
    'sections' => [
        'details' => 'Event Details',
        'description' => 'Description',
    ],
    'fields' => [
        'title_en' => 'Title (English)',
        'title_ar' => 'Title (Arabic)',
        'date' => 'Date',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'category' => 'Category',
        'link' => 'Link',
        'link_helper' => 'Internal path (e.g. /blog/my-post) or full external URL (e.g. https://example.com)',
        'description_en' => 'Description (English)',
        'description_ar' => 'Description (Arabic)',
    ],
    'uncategorized' => 'Uncategorized',
    'validation' => [
        'link_format' => 'Link must start with / (internal path) or http:// / https:// (external URL).',
        'end_after_start' => 'End time must be after start time.',
    ],
    'columns' => [
        'title' => 'Title',
        'date' => 'Date',
        'time' => 'Time',
        'category' => 'Category',
        'link' => 'Link',
    ],
    'filters' => [
        'category' => 'Category',
    ],
    'empty_state' => [
        'heading' => 'No events yet',
        'description' => 'Create your first event to see it on the public calendar.',
    ],
    'actions' => [
        'create_first' => 'Create first event',
    ],
    'calendar' => [
        'title' => 'Events Calendar',
        'subtitle' => 'Browse upcoming events, meetings, and important dates.',
        'today' => 'Today',
        'search_placeholder' => 'Search events...',
        'all_categories' => 'All',
        'no_events_day' => 'No events on this day.',
        'more' => ':count more',
        'no_link' => 'No link attached.',
    ],
];

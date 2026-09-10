<?php

return [
    'navigation' => [
        'label' => 'Menu',
        'plural' => 'Menus',
    ],
    'sections' => [
        'information' => 'Menu Information',
    ],
    'fields' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'location' => 'Location',
        'location_helper' => 'Where this menu renders, e.g. "header" or "footer".',
    ],
    'columns' => [
        'name' => 'Name',
        'location' => 'Location',
        'items_count' => 'Items',
    ],
    'actions' => [
        'create_first' => 'Create your first menu',
    ],
    'empty_state' => [
        'heading' => 'No menus yet',
        'description' => 'Create a menu to control site navigation.',
    ],
];

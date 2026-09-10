<?php

return [
    'navigation' => [
        'label' => 'Category',
        'plural' => 'Categories',
    ],
    'sections' => [
        'information' => 'Category Information',
    ],
    'fields' => [
        'name_en' => 'Name (English)',
        'name_ar' => 'Name (Arabic)',
        'slug' => 'Slug',
        'description_en' => 'Description (English)',
        'description_ar' => 'Description (Arabic)',
        'parent' => 'Parent Category',
        'order' => 'Order',
    ],
    'columns' => [
        'name' => 'Name',
        'parent' => 'Parent',
        'posts_count' => 'Posts',
        'order' => 'Order',
    ],
    'actions' => [
        'create_first' => 'Create your first category',
    ],
    'empty_state' => [
        'heading' => 'No categories yet',
        'description' => 'Create categories to organize blog posts.',
    ],
    'notifications' => [
        'cannot_delete' => 'Cannot delete this category',
        'has_posts' => 'This category still has posts assigned to it.',
    ],
];

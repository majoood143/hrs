<?php

return [
    'navigation' => [
        'label' => 'Partner',
        'plural' => 'Partners',
    ],
    'sections' => [
        'information' => 'Partner Information',
    ],
    'fields' => [
        'name_en' => 'Name (English)',
        'name_ar' => 'Name (Arabic)',
        'logo' => 'Logo',
        'website_url' => 'Website URL',
        'description_en' => 'Description (English)',
        'description_ar' => 'Description (Arabic)',
        'type' => 'Partner Type',
        'is_active' => 'Active',
        'order' => 'Order',
    ],
    'types' => [
        'stable' => 'Stable / Farm',
        'transport' => 'Transport Operator',
        'veterinary' => 'Veterinary',
        'authority' => 'Registration Authority',
    ],
    'columns' => [
        'logo' => 'Logo',
        'name' => 'Name',
        'type' => 'Type',
        'is_active' => 'Active',
        'order' => 'Order',
    ],
    'filters' => [
        'type' => 'Type',
    ],
    'actions' => [
        'create_first' => 'Add your first partner',
    ],
    'empty_state' => [
        'heading' => 'No partners yet',
        'description' => 'Add the stables and operators you work with.',
    ],
];

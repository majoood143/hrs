<?php

return [
    'navigation' => [
        'label' => 'Service',
        'plural' => 'Services',
    ],

    'fields' => [
        'name' => 'Name (English)',
        'ar_name' => 'Name (Arabic)',
        'description' => 'Description (English)',
        'ar_description' => 'Description (Arabic)',
        'price' => 'Price',
        'price_helper' => 'The fixed price the customer pays, before the service fee and VAT. Use 0 for a free service.',
        'is_active' => 'Active',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
    ],

    'in_use' => [
        'title' => 'Still in use',
        'body' => 'Orders, transactions or fee rules use this service. Turn it off instead.',
        'bulk' => 'Some were not deleted: orders, transactions or fee rules still use them.',
    ],
];

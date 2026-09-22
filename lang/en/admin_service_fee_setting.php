<?php

return [
    'navigation' => [
        'label' => 'Service Fee',
        'plural' => 'Service Fees',
    ],

    'types' => [
        'percentage' => 'Percentage of the price',
        'fixed' => 'Fixed amount',
    ],

    'scope' => [
        'global' => 'Global (all services)',
        'service' => 'Service: :name',
        'form' => 'Form #:id',
    ],

    'fields' => [
        'name' => 'Name',
        'description' => 'Description',
        'fee_type' => 'Fee type',
        'fee_value' => 'Value',
        'fee_value_helper' => 'A percentage (e.g. 5) or an amount in the site currency (e.g. 0.500).',
        'service' => 'Service',
        'service_helper' => 'Leave empty for a fee on every service.',
        'form' => 'Form',
        'form_helper' => 'Leave empty unless this fee is only for one form. A form fee beats a service fee, which beats the global one.',
        'is_active' => 'Active',
        'effective_from' => 'Effective from',
        'effective_to' => 'Effective until',
    ],

    'columns' => [
        'scope' => 'Applies to',
        'rate' => 'Fee',
    ],

    'notice' => 'The fee is added on top of the service price and is shown to the customer. Existing orders keep the fee they were created with.',
];

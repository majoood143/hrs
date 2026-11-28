<?php

return [
    'discount' => 'Promo code discount',

    'errors' => [
        'invalid' => 'This promo code is not valid.',
        'other_stable' => 'This promo code is for another stable.',
        'not_started' => 'This promo code cannot be used yet.',
        'expired' => 'This promo code has expired.',
        'other_mobile' => 'This promo code belongs to another mobile number.',
        'used_up' => 'This promo code has been used up.',
        'online_only' => 'A promo code is taken off an online payment only. Choose to pay online, or remove the code.',
    ],

    'site' => [
        'label' => 'Promo code',
        'placeholder' => 'e.g. SAVE10',
        'apply' => 'Apply',
        'applied' => 'Promo code applied.',
    ],

    'admin' => [
        'navigation' => 'Promo codes',
        'singular' => 'Promo code',
        'plural' => 'Promo codes',
        'notice' => 'The discount comes off the price of a stable session or lesson package (online payment only); the service fee, the stable\'s commission and VAT are worked out on what is left.',
        'sections' => [
            'details' => 'Code',
            'discount' => 'Discount',
            'validity' => 'Validity',
            'scope' => 'Who can use it',
        ],
        'fields' => [
            'code' => 'Code',
            'code_helper' => 'Letters and digits; saved in capitals. Customers can type it in any case.',
            'name' => 'Name',
            'description' => 'Note (admins only)',
            'discount_type' => 'Discount type',
            'discount_value_percentage' => 'Discount (%)',
            'discount_value_fixed' => 'Discount amount',
            'max_discount_amount' => 'Maximum discount',
            'max_discount_amount_helper' => 'Optional cap on a percentage, e.g. 50% up to 20.',
            'valid_from' => 'Valid from',
            'valid_until' => 'Valid until',
            'max_uses' => 'Maximum uses',
            'max_uses_helper' => 'Leave empty for unlimited. An unpaid checkout that expires gives its use back.',
            'is_active' => 'Active',
            'stable' => 'Stable',
            'stable_helper' => 'Leave empty for every stable. The stable earns less by the discount, so agree it with them.',
            'restricted_mobile' => 'Only for this mobile',
            'restricted_mobile_helper' => 'Optional: the code then works only for the customer signed in with this number (game winners\' codes).',
            'used' => 'Uses',
            'source' => 'Made by',
        ],
        'types' => [
            'percentage' => 'Percentage (%)',
            'fixed' => 'Fixed amount',
        ],
        'sources' => [
            'admin' => 'Admin',
            'game' => 'Game prize',
        ],
        'all_stables' => 'Every stable',
        'no_expiry' => 'No expiry',
        'after_discount' => 'After a :amount discount (promo code :code)',
    ],
];

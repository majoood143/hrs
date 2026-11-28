<?php

return [
    'paid_at_desk' => 'Paid at the stable desk',

    'methods' => [
        'cash' => 'Cash',
        'card' => 'Card machine',
        'bank_transfer' => 'Bank transfer',
        'wallet' => 'Mobile wallet',
        'link' => 'Payment link',
    ],

    'statuses' => [
        'paid' => 'Paid',
        'free' => 'Free',
        'refunded' => 'Refunded',
        'partly_refunded' => 'Partly refunded',
        'awaiting_payment' => 'Awaiting payment',
        'cancelled' => 'Cancelled',
    ],

    'errors' => [
        'closed' => 'The desk is not open for this stable.',
        'method' => 'This stable\'s desk does not take that payment method.',
        'total' => 'The payments come to :paid, the total is :total.',
        'changed' => 'The price changed meanwhile. Check the total and try again.',
        'not_refundable' => 'Only a sale paid at the desk can be refunded here.',
        'phone' => 'Enter a valid phone number.',
    ],

    'admin' => [
        'saved' => 'Saved.',
        'section' => 'Stable desk (POS)',
        'section_desc' => 'Stables switched on (admin → the stable) can sell sessions and lesson packages at their desk from the app. We take the commission only on these sales, no service fee.',
        'methods' => 'Payment methods stables may take',
        'methods_help' => 'Each stable\'s owner then picks which of these its desk takes (stable panel → Booking settings).',
        'stable_switch' => 'Desk sales (POS)',
        'stable_switch_help' => 'Lets this stable sell at its desk from the app. Also needs General Settings → Modules → POS.',
        'stable_methods' => 'Desk payment methods',
        'stable_methods_help' => 'What your desk takes when you sell from the app (My stable → New sale).',
    ],
];

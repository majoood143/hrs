<?php

// Extras added to a booked session (owner panel, admin, booking form, receipts, insights).
// Keep lang/ar/stable_extras.php in step.
return [
    'label' => 'Extra',
    'plural' => 'Extras',
    'add_heading' => 'Add extras',
    'add_hint' => 'Optional, added to this booking’s price.',
    'per_rider' => 'per rider',
    'per_booking' => 'per booking',
    'update_price' => 'Update the price',
    'line' => ':name × :quantity',

    'pricing' => [
        'per_booking' => 'Per booking',
        'per_rider' => 'Per rider',
    ],

    'fields' => [
        'quantity' => 'Quantity',
        'amount' => 'Amount',
        'price' => 'Price',
        'pricing' => 'Charged',
        'max_quantity' => 'Most per booking',
        'offerings' => 'For these services',
        'sort' => 'Order',
        'sold' => 'Sold',
        'revenue' => 'Revenue',
    ],

    'placeholders' => [
        'en_name' => 'e.g. Photo session',
        'ar_name' => 'مثال: جلسة تصوير',
        'en_description' => 'e.g. 20 edited photos sent by WhatsApp after the session',
        'ar_description' => 'مثال: ٢٠ صورة معدّلة تُرسل عبر واتساب بعد الجلسة',
        'price' => 'e.g. 5.000',
        'max_quantity' => 'e.g. 1',
        'offerings' => 'Every service',
        'sort' => 'e.g. 1',
    ],

    'hints' => [
        'pricing' => 'Per rider: the price is charged for each rider of the booking.',
        'max_quantity' => 'How many the customer may take in one booking (1 = a tick box).',
        'offerings' => 'Leave empty to offer it with every service.',
        'photo' => 'Optional. Recommended 800 × 600 px, JPG, PNG or WebP, up to 4 MB.',
        'active' => 'Off: customers no longer see it; past bookings keep it.',
        'commission' => 'Extras are part of the booking’s price: the same commission and VAT apply.',
    ],

    'panel' => [
        'empty_heading' => 'No extras yet',
        'empty_description' => 'Add photos, helmet hire, transport or anything else a customer can add to a session.',
        'off_notice' => 'Extras are switched off for this stable by the site’s team: customers do not see them.',
    ],

    'admin' => [
        'title' => 'Extras',
        'stable_switch' => 'Extras on bookings',
        'stable_switch_help' => 'Off: this stable’s customers cannot add extras, whatever its owner has set up.',
        'saved' => 'Saved',
    ],

    'errors' => [
        'unavailable' => 'One of the extras you chose is no longer available. Please check your choice.',
        'too_many' => 'You can add at most :max of “:name”.',
        'with_package' => 'Extras cannot be added to a session booked with a package.',
    ],

    'insights' => [
        'title' => 'Extras',
        'description' => 'What customers add to their sessions.',
        'revenue' => 'Extras revenue',
        'attach_rate' => 'Bookings with an extra',
        'top' => 'Top extras',
        'none' => 'No extras sold in this period.',
        'stable' => 'Stables selling the most',
    ],
];

<?php

return [
    'title' => 'Booking ticket',
    'filename' => 'ticket',
    'download' => 'Download ticket (PDF)',
    'qr_label' => 'QR code of booking :reference',
    'service' => 'Session',
    'date' => 'Date',
    'time' => 'Time',
    // "من … إلى …" in Arabic: two bare times side by side come out reversed in a right-to-left PDF
    'time_range' => ':start – :end',
    'riders' => 'Riders',
    'trainer' => 'Trainer',
    'customer' => 'Booked by',
    'payment' => 'Payment',
    'location' => 'Location',
    'map' => 'Open the map',
    'mail_attached' => 'Your ticket is attached (PDF): show its QR code at the stable to check in.',
    'show_qr' => 'Show this code at the stable to check in.',
    'status' => [
        'confirmed' => 'Confirmed',
        'completed' => 'Checked in',
    ],

    // the page a scanned code opens for anyone who is not the stable's staff
    'landing' => [
        'title' => 'Booking :reference',
        'intro' => 'This is a booking ticket for :stable.',
        'staff' => 'Stable staff: check in',
        'customer' => 'Is this your booking? See it in your account.',
    ],

    // the stable panel's check-in page
    'check_in' => [
        'title' => 'Check in',
        'reference' => 'Booking reference',
        'reference_placeholder' => 'e.g. BK-7Q2M9XKD, or paste the ticket link',
        'reference_hint' => 'Scan the QR code on the customer\'s ticket with your phone camera, or type the reference printed under it.',
        'find' => 'Find booking',
        'not_found' => 'No booking :reference at this stable.',
        'other_stable' => 'This booking is for :stable.',
        'open_other' => 'Check in at :stable',
        'ready' => 'Ready to check in',
        'collect' => 'Collect :total before the session (pay at the stable).',
        'not_yet' => 'This booking is for :date: it can be checked in from that day.',
        'already' => 'Already checked in (:time).',
        'no_show' => 'Marked as a no-show. Check in to correct it.',
        'pending' => 'Not confirmed yet: the customer has not paid.',
        'cancelled' => 'This booking was cancelled.',
        'check_in' => 'Check in',
        'done' => 'Checked in: enjoy the session!',
        'another' => 'Check in another',
    ],
];

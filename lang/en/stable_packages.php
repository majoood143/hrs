<?php

// Lesson packages: selling them (owner panel), buying and using them (site, account), and the
// messages about them. Keep lang/ar/stable_packages.php in step.
return [
    'eyebrow' => 'Lesson packages',
    'buy' => 'Buy this package',
    'buy_now' => 'Buy and pay',
    'sessions_count' => '{1} :count session|[2,*] :count sessions',
    'sessions_left' => '{0} No sessions left|{1} :count session left|[2,*] :count sessions left',
    'of_total' => 'of :total',
    'valid_days' => '{1} Valid :count day|[2,*] Valid :count days',
    'valid_until' => 'Valid until :date',
    'saving' => 'Save :amount on single sessions',
    'sign_in_note' => 'A package is kept in your account: you sign in with your phone to buy and use it.',
    'terms' => 'The sessions can be booked for sessions up to :days days after you pay. Unused sessions are not refunded.',
    'use_package' => 'Use my package: :name',
    'left_until' => '{1} :count session left, until :date|[2,*] :count sessions left, until :date',
    'paid_with_package' => 'Package',

    'status' => [
        'pending' => 'Awaiting payment',
        'active' => 'Active',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
    ],

    'card' => [
        'used' => 'Booked with your package :name.',
    ],

    'account' => [
        'title' => 'My packages',
        'none' => 'You have no lesson packages yet.',
        'browse' => 'Find sessions',
        'book' => 'Book a session',
        'ready' => 'Your package is ready to use.',
    ],

    'fields' => [
        'reference' => 'Package no.',
        'package' => 'Package',
        'sessions' => 'Sessions',
        'price' => 'Package price',
        'validity_days' => 'Valid for',
        'expires' => 'Valid until',
    ],

    'panel' => [
        'label' => 'Package',
        'plural' => 'Lesson packages',
        'sessions_hint' => 'One session is one rider in one slot.',
        'price_hint' => 'The whole package. Our service fee and VAT are added at checkout; your commission applies as for single sessions.',
        'validity_hint' => 'From the day it is paid.',
        'days' => 'days',
        'active_hint' => 'Switched off, it is no longer sold; packages already bought can still be used.',
        'sold' => 'Sold',
        'in_use' => ':count in use',
        'empty_heading' => 'No packages yet',
        'empty_description' => 'Sell several sessions together, e.g. 8 lessons for the price of 7.',
    ],

    'purchases' => [
        'label' => 'Package bought',
        'plural' => 'Packages bought',
        'left' => 'Sessions left',
        'empty' => 'No packages bought yet.',
    ],

    'errors' => [
        'not_available' => 'This package cannot be bought at the moment.',
        'not_usable' => 'This package cannot be used for this session.',
        'expires_before' => 'Your package expires on :date, before this session.',
        'sessions_left' => '{0} Your package has no sessions left.|{1} Your package has only :count session left.|[2,*] Your package has only :count sessions left.',
    ],

    'sms' => [
        'ready' => ':site: your package :package at :stable is ready: :sessions sessions until :date. Book: :url',
        'sold' => 'Package sold :reference: :package (:sessions sessions) to :customer :phone, valid until :date.',
    ],

    'mail' => [
        'customer' => [
            'subject' => 'Your package :package is ready',
            'heading' => 'Your package is ready',
            'intro' => 'Thank you. You can now book :sessions sessions at :stable until :date.',
            'button' => 'Book a session',
        ],
        'stable' => [
            'subject' => 'Package sold: :package',
            'heading' => 'A package was sold',
            'intro' => ':customer bought :package.',
            'button' => 'Open your stable panel',
        ],
    ],
];

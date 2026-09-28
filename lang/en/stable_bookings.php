<?php

// Stable bookings on the public site (search, service page, booking form, order card) and the
// messages about a booking to the customer and the stable. Keep lang/ar/stable_bookings.php in step.
return [
    'book' => 'Book',
    'price' => 'Price',
    'per_rider' => 'per rider',
    'free' => 'Free',
    'private' => 'Private session',
    'group_of' => '{1} Up to :count rider|[2,*] Up to :count riders',
    'riders_count' => '{1} :count rider|[2,*] :count riders',
    'places_left' => '{1} :count place left|[2,*] :count places left',
    'with_trainer' => 'with :name',
    'directions' => 'Directions',
    'update' => 'Update',

    'search' => [
        'eyebrow' => 'Book a session',
        'title' => 'Book riding sessions',
        'intro' => 'Find open times at stables near you, choose a time and book in a minute.',
        'date' => 'Date',
        'riders' => 'Riders',
        'time' => 'Time of day',
        'any_time' => 'Any time',
        'times' => [
            'morning' => 'Morning (before 12:00)',
            'afternoon' => 'Afternoon (12:00–17:00)',
            'evening' => 'Evening (from 17:00)',
        ],
        'button' => 'Search',
        'week' => 'The next seven days',
        'times_count' => '{0} full|{1} :count time|[2,*] :count times',
        'none_title' => 'Nothing open on this day',
        'none_body' => 'Try another day, fewer riders, or a wider area.',
        'next_date' => 'Next opening: :date',
    ],

    'stable_page' => [
        'heading' => 'Book a session here',
        'pick_time' => 'Choose a time',
    ],

    'offering' => [
        'pick_day' => 'Choose a day',
        'no_days' => 'There are no open times at the moment. Please check again soon.',
        'day_full' => 'Every time on this day is taken. Please choose another day.',
    ],

    'form' => [
        'eyebrow' => 'Your booking',
        'title' => 'Complete your booking',
        'change_time' => 'Choose another time',
        'rider' => 'Rider',
        'rider_n' => 'Rider :n',
        'contact' => 'How we reach you',
        'your_name' => 'Your name',
        'email' => 'Email (optional)',
        'phone_hint' => 'Your confirmation comes by SMS. Sign in with this number later to see or cancel the booking.',
        'payment' => 'How you pay',
        'waiver' => 'Waiver',
        'waiver_accept' => 'I have read and accept the waiver for every rider in this booking.',
        'summary' => 'Summary',
        'submit' => 'Confirm booking',
        'booked' => 'Your booking is confirmed. We sent you the details by SMS.',
        'check_errors' => 'Please check the details below.',
    ],

    'rider' => [
        'name' => 'Full name',
        'age' => 'Age',
        'level' => 'Riding level',
        'weight' => 'Weight (kg)',
        'height' => 'Height (cm)',
        'guardian_name' => 'Guardian\'s name',
        'guardian_phone' => 'Guardian\'s phone',
        'notes' => 'Notes for the trainer (health, experience…)',
    ],

    'levels' => [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
    ],

    'payment_options' => [
        'online' => 'Pay online now',
        'online_hint' => 'By card, on the secure payment page. Your places are confirmed as soon as it is paid.',
        'at_stable' => 'Pay at the stable',
        'at_stable_hint' => 'Your places are confirmed now; pay the stable on the day.',
    ],

    'policy' => [
        'cancel_until' => 'Free cancellation online up to :hours hours before the session. The service fee is not refunded.',
        'no_online_cancel' => 'To change or cancel, contact the stable.',
    ],

    'card' => [
        'eyebrow' => 'Booking',
        'pay_at_stable' => 'Pay :total at the stable on the day.',
        'cancelled_by_stable' => 'The stable cancelled this session.',
        'cancel' => 'Cancel booking',
        'cancel_confirm' => 'Cancel this booking? This cannot be undone.',
        'cancelled' => 'Your booking was cancelled.',
        'sign_in_to_cancel' => 'Sign in with your phone to cancel',
    ],

    'errors' => [
        'no_riders' => 'Please add at least one rider.',
        'slot_gone' => 'This time is no longer available.',
        'not_bookable' => 'This time cannot be booked at the moment.',
        'closed' => 'Bookings for this time have closed.',
        'riders_range' => 'This session takes :min to :max riders per booking.',
        'only_left' => '{1} Only :count place is left at this time.|[2,*] Only :count places are left at this time.',
        'full' => 'This time is fully booked.',
        'payment_option' => 'Please choose how you pay.',
        'cannot_cancel' => 'This booking can no longer be cancelled online. Please contact the stable.',
        'not_confirmed' => 'Only a confirmed booking can be marked this way.',
    ],

    'fields' => [
        'reference' => 'Booking',
        'session' => 'Session',
        'stable' => 'Stable',
        'date' => 'Date',
        'time' => 'Time',
        'riders' => 'Riders',
        'customer' => 'Customer',
        'payment' => 'Payment',
        'reason' => 'Reason',
    ],

    'payment' => [
        'paid_online' => 'Paid online',
        'pending' => 'Awaiting payment',
        'at_stable' => 'To pay at the stable: :total',
        'free' => 'Free',
        'package' => 'Paid with package :reference',
    ],

    'cancelled_by' => [
        'customer' => 'the customer',
        'stable' => 'the stable',
        'expired' => 'the system (unpaid)',
    ],

    'sms' => [
        'reminder' => ':site: reminder: :offering at :stable, :date :time, :riders. Details: :url',
        'reminder_map' => ':site: reminder: :offering at :stable, :date :time, :riders. Directions: :map Details: :url',
        'customer_confirmed' => ':site: booking :reference confirmed. :offering at :stable, :date :time, :riders. :payment. Details: :url',
        'customer_cancelled_customer' => ':site: booking :reference (:date :time) is cancelled. Details: :url',
        'customer_cancelled_stable' => ':site: :stable cancelled your booking :reference (:date :time). :reason Details: :url',
    ],

    // to the stable: SMS, WhatsApp (sent in English then Arabic) and the email's summary
    'alert' => [
        'confirmed' => 'New booking :reference: :offering, :date :time, :riders (:names). Customer: :customer :phone. :payment. :panel',
        'cancelled' => 'Booking :reference (:offering, :date :time, :riders) was cancelled by :who.',
        'refund_note' => 'It was paid: a refund is due.',
    ],

    'mail' => [
        'customer_button' => 'View my booking',
        'stable_button' => 'Open your bookings',
        'customer_refund' => 'Your payment will be refunded (the service fee is not refundable). We will let you know when it is done.',
        'stable_refund' => 'This booking was paid, so a refund is due.',
        'customer_confirmed' => [
            'subject' => 'Booking :reference confirmed: :offering on :date',
            'heading' => 'Your booking is confirmed',
            'intro' => 'Thank you. Your places at :stable are booked.',
        ],
        'customer_cancelled' => [
            'subject' => 'Booking :reference cancelled',
            'heading' => 'Your booking is cancelled',
            'intro' => 'Your booking at :stable was cancelled by :who.',
        ],
        'stable_confirmed' => [
            'subject' => 'New booking :reference: :date :time',
            'heading' => 'New booking',
            'intro' => ':customer booked :offering.',
        ],
        'stable_cancelled' => [
            'subject' => 'Booking :reference cancelled',
            'heading' => 'A booking was cancelled',
            'intro' => 'This booking was cancelled by :who.',
        ],
    ],
];

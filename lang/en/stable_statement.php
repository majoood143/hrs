<?php

// A stable's account with us: the statement (owner panel, admin page, PDF/CSV), the balances page,
// payouts and receipts, and the messages about them. Keep lang/ar/stable_statement.php in step.
return [
    'title' => 'Stable statement',
    'empty' => 'No bookings with money in this period.',
    'no_settlements' => 'No payouts or payments recorded in this period.',
    'note' => 'Bookings paid online through us leave us owing the stable its share (the price and its VAT, less any refund and the commission). Bookings paid into the stable\'s own gateway, or at the stable once the rider is marked attended, leave the stable owing us the service fee, its VAT and the commission. Payouts and payments are recorded when the money has moved.',
    'unmarked' => '{1} :count past pay-at-the-stable booking is not marked attended or no-show yet: it is not in the statement until it is.|[2,*] :count past pay-at-the-stable bookings are not marked attended or no-show yet: they are not in the statement until they are.',

    'sections' => [
        'balance' => 'Balance',
        'bookings' => 'Bookings',
        'settlements' => 'Payouts and payments',
    ],

    'cards' => [
        'bookings' => 'Bookings',
        'collected' => 'Customers paid',
        'collected_hint' => 'Through us: :ours · to the stable: :yours',
        'stable_share' => 'Stable\'s share',
        'stable_share_hint' => 'The price and its VAT, less refunds and commission',
        'commission' => 'Commission',
        'commission_hint' => 'Plus service fees (with VAT) of :fee',
    ],

    'summary' => [
        'opening' => 'Balance brought forward',
        'owed_to_stable' => 'Stable\'s share of bookings paid through us',
        'owed_by_stable' => 'Fees and commission on bookings the stable collected',
        'payouts' => 'Paid to the stable',
        'receipts' => 'Received from the stable',
        'closing' => 'Balance carried forward',
    ],

    'balance' => [
        'we_owe' => 'We owe the stable :amount.',
        'you_owe' => 'The stable owes us :amount.',
        'settled' => 'Nothing is owed either way.',
    ],

    'kinds' => [
        'online_ours' => 'Online, through us',
        'online_own' => 'Online, stable\'s gateway',
        'at_stable' => 'At the stable',
    ],

    'directions' => [
        'payout' => 'Paid to the stable',
        'receipt' => 'Received from the stable',
    ],

    'methods' => [
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
        'other' => 'Other',
    ],

    'fields' => [
        'stable' => 'Stable',
        'booking' => 'Booking',
        'session' => 'Session',
        'riders' => 'Riders',
        'paid_how' => 'Paid',
        'collected' => 'Paid by the customer',
        'refunded' => 'Refunded',
        'stable_share' => 'Stable\'s share',
        'fee' => 'Service fee + VAT',
        'commission' => 'Commission',
        'net' => 'Balance effect',
        'total' => 'Total',
        'amount' => 'Amount',
        'paid_on' => 'Date',
        'direction' => 'Type',
        'method' => 'Method',
        'reference' => 'Reference',
        'note' => 'Note',
        'recorded_by' => 'Recorded by',
    ],

    'widget' => [
        'heading' => 'This month',
        'this_month' => 'This month',
        'bookings' => 'Bookings',
        'stable_share' => 'Your share',
        'commission' => 'Commission',
        'rate' => 'At :rate',
        'balance' => 'Balance with us',
        'balance_we_owe' => 'We owe you this',
        'balance_you_owe' => 'You owe us this',
        'balance_settled' => 'All settled',
        'today' => 'Today\'s bookings',
        'today_empty' => 'No bookings today',
    ],

    'admin' => [
        'title' => 'Stable balances',
        'balance' => 'Balance',
        'we_owe' => 'we owe the stable',
        'they_owe' => 'the stable owes us',
        'settled' => 'settled',
        'collection' => 'Online payments',
        'collects_ours' => 'Through us',
        'collects_own' => 'Own :gateway',
        'last_settlement' => 'Last payout / payment',
        'statement' => 'Statement',
        'record' => 'Record',
        'record_payout' => 'Record a payout',
        'record_payout_hint' => 'Money we paid the stable (its share of bookings we collected). Record it once the transfer is made; the owner is told.',
        'record_receipt' => 'Record a payment received',
        'record_receipt_hint' => 'Money the stable paid us (fees and commission on bookings it collected). Record it once it has arrived; the owner is told.',
        'recorded' => 'Recorded',
        'remove' => 'Remove',
        'remove_hint' => 'Only for a mistake: the balance changes back. The owner is not told.',
        'removed' => 'Removed',
        'total_we_owe' => 'We owe stables',
        'total_we_owe_hint' => 'Their share of bookings paid through us, less payouts',
        'total_they_owe' => 'Stables owe us',
        'total_they_owe_hint' => 'Fees and commission on bookings they collected, less payments',
        'pick_stable' => 'Choose a stable.',
        'empty' => 'No stable has bookings with money yet.',
    ],

    'sms' => [
        'payout' => 'We paid you :amount for :stable on :date. See your statement in your stable panel.',
        'receipt' => 'We received your payment of :amount for :stable on :date. Thank you.',
    ],

    'mail' => [
        'button' => 'Open your statement',
        'payout' => [
            'subject' => 'We paid you :amount',
            'heading' => 'We paid you :amount',
            'intro' => 'We paid :amount for :stable on :date: your share of bookings paid through us.',
        ],
        'receipt' => [
            'subject' => 'Payment of :amount received',
            'heading' => 'We received :amount',
            'intro' => 'We received :amount from :stable on :date for the fees and commission on bookings you collected. Thank you.',
        ],
    ],
];

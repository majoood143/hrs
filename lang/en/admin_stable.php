<?php

return [
    'navigation' => [
        'label' => 'Stable',
        'plural' => 'Stables',
    ],

    'tabs' => [
        'owner' => 'Owner & approval',
    ],

    'fields' => [
        'approval_status' => 'Approval',
        'commission' => 'Commission',
        'commission_type' => 'Commission type',
        'commission_value' => 'Commission',
        'owners' => 'Owners',
        'owner' => 'Stable owner',
        'no_owner' => 'No owner account (added by an admin)',
        'reason' => 'Reason given to the owner',
        'approved_at' => 'Approved at',
    ],

    'approval' => [
        'pending_badge' => 'Stables waiting for approval',
        'approve' => 'Approve',
        'approve_heading' => 'Approve :stable',
        'approve_description' => 'Every stable has its own commission, taken from its share of each booking. The stable goes live on the site and its owner is told by email and SMS.',
        'approved' => 'Stable approved',
        'set_commission' => 'Commission',
        'commission_description' => 'Applies to new bookings. Bookings already made keep the commission they were made with.',
        'commission_hint' => 'A percentage of the booking price, or a fixed amount per booking.',
        'commission_saved' => 'Commission saved',
        'no_commission' => 'Not set: the stable cannot take bookings yet',
        'reject' => 'Reject',
        'rejected' => 'Stable rejected',
        'suspend' => 'Suspend',
        'suspend_description' => 'Hides the stable from the site and stops new bookings. Bookings already made stay.',
        'suspended' => 'Stable suspended',
        'reason' => 'Reason',
        'reason_hint' => 'The owner sees this.',
        'link_owner' => 'Link an owner',
        'link_owner_description' => 'Give this stable to a registered stable owner, e.g. when an existing stable\'s owner signed up (then reject the copy they registered).',
        'owner_linked' => 'Owner linked',
    ],

    'offerings' => [
        'title' => 'Bookable services',
        'name' => 'Service',
        'price' => 'Price per rider',
        'duration' => 'Duration',
        'capacity' => 'Places per slot',
        'active' => 'Active',
    ],

    'payments' => [
        'title' => 'Gateway accounts',
        'gateway' => 'Gateway',
        'in_use' => 'Chosen by the stable',
        'test_mode' => 'Test mode',
        'status' => 'Approval',
        'last_test' => 'Last check',
        'reviewed' => 'Reviewed',
        'keys' => 'Keys',
        'test' => 'Check keys',
        'approve' => 'Approve',
        'approve_description' => 'Online payments for this stable will go to this account, and the stable will owe us the service fee and its commission on them.',
        'approve_test_mode' => 'These are test-mode keys: payments would go to a sandbox and no real money would be collected. Approve only for testing.',
        'approved' => 'Gateway keys approved',
        'reject' => 'Reject',
        'rejected' => 'Gateway keys rejected',
        'empty' => 'The stable collects through us: no own gateway keys.',
    ],

    'admin_mode' => [
        'open' => 'Open stable panel',
    ],
];

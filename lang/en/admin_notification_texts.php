<?php

return [
    'title' => 'Notification Texts',

    'groups' => [
        'sms' => 'SMS messages',
        'sms_desc' => 'One short message each. The order number, link and amount must stay in the text.',
        'received' => 'Email: order received or paid',
        'received_desc' => 'Sent when an order comes in (with the receipt attached if it was paid).',
        'completed' => 'Email: order completed',
        'completed_desc' => 'Sent when an admin completes the order.',
        'rejected' => 'Email: request rejected',
        'rejected_desc' => 'Sent when a reviewer rejects the request. The reviewer\'s reason is added automatically.',
        'refunded' => 'Email: refund processed',
        'refunded_desc' => 'Sent each time a refund is recorded.',
        'common' => 'Email: greeting and button',
        'common_desc' => 'Used by all the customer emails above.',
    ],

    'labels' => [
        'sms.received_paid' => 'SMS: payment received',
        'sms.received_free' => 'SMS: request received (free)',
        'sms.completed' => 'SMS: order completed',
        'sms.rejected' => 'SMS: request rejected',
        'sms.refunded' => 'SMS: refund processed',
        'mail.received.subject_paid' => 'Subject (paid)',
        'mail.received.subject_free' => 'Subject (free)',
        'mail.received.intro_paid' => 'Opening line (paid)',
        'mail.received.intro_free' => 'Opening line (free)',
        'mail.received.receipt_attached' => 'Receipt note',
        'mail.received.next' => 'What happens next',
        'mail.completed.subject' => 'Subject',
        'mail.completed.intro' => 'Opening line',
        'mail.completed.next' => 'Closing line',
        'mail.rejected.subject' => 'Subject',
        'mail.rejected.intro' => 'Opening line',
        'mail.rejected.reason' => '"Reason" heading',
        'mail.rejected.refund' => 'Refund line',
        'mail.refunded.subject' => 'Subject',
        'mail.refunded.intro' => 'Opening line',
        'mail.refunded.timing' => 'Timing line',
        'mail.refunded.fee_kept' => 'Service fee line',
        'mail.greeting' => 'Greeting (with name)',
        'mail.greeting_guest' => 'Greeting (no name)',
        'mail.track_button' => 'Button label',
    ],

    'placeholders' => 'Fills in automatically: :names',
    'no_placeholders' => 'A plain text, nothing to fill in.',

    'errors' => [
        'unknown' => 'These placeholders are not available here: :names. You can use: :allowed.',
        'missing' => 'This text must keep :names (the customer needs it).',
    ],

    'actions' => [
        'save' => 'Save texts',
        'reset' => 'Reset all to defaults',
        'reset_desc' => 'Every text you changed goes back to its original wording.',
    ],

    'notifications' => [
        'saved' => 'The texts were saved. They apply to the next messages sent.',
        'reset' => 'All texts are back to their defaults.',
    ],
];

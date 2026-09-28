<?php

return [
    'tab' => 'WhatsApp',

    'sections' => [
        'alerts' => 'Admin alerts on WhatsApp',
        'alerts_desc' => 'Send a WhatsApp message to the admin numbers below whenever a visitor publishes a new post, with a link to the post. Messages are in English and Arabic.',
        'recipients' => 'Admin numbers',
        'recipients_desc' => 'Every number here receives the alerts.',
        'types' => 'Which new posts send an alert',
    ],

    'fields' => [
        'enabled' => 'Send WhatsApp alerts',
        'instance' => 'Sending WhatsApp number (instance)',
        'instance_helper' => 'Connect a number under WhatsApp → Instances by scanning its QR code. Left empty, the first connected instance is used.',
        'instance_placeholder' => 'First connected instance',
        'recipient_name' => 'Name',
        'recipient_phone' => 'WhatsApp number',
        'recipient_phone_helper' => 'With country code; an 8-digit number is taken as Omani.',
        'add_recipient' => 'Add number',
        'test_phone' => 'Send the test to',
    ],

    'api' => [
        'configured' => 'Evolution API: set up (:url).',
        'missing' => 'Evolution API: not set up. Add EVOLUTION_URL and EVOLUTION_API_KEY to the server\'s .env.',
    ],

    'actions' => [
        'test' => 'Send a test message',
        'test_desc' => 'Uses the saved settings: save your changes first.',
    ],

    'notifications' => [
        'bad_phone' => 'That phone number is not valid.',
        'test_sent' => 'Test message sent.',
        'test_failed' => 'The test message could not be sent.',
    ],
];

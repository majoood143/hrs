<?php

return [
    'title' => 'SMS Settings',

    'sections' => [
        'driver' => 'SMS channel',
        'driver_desc' => 'How text messages are sent. Customers get a sign-in code by SMS, and (soon) their order number and completion notice.',
        'tamimah' => 'Tamimah SMS',
        'tamimah_desc' => 'Credentials from your Tamimah Telecom account. Passwords are stored encrypted.',
    ],

    'drivers' => [
        'none' => 'Off: SMS cannot be sent',
        'tamimah' => 'Tamimah SMS (real messages)',
        'demo' => 'Demo: show the code on screen, send nothing',
        'log' => 'Log: write messages to the application log (development)',
    ],

    'demo_warning' => 'Demo mode is for presentations only: sign-in codes appear on the page instead of being sent, so anyone can sign in as any phone number. Switch to Tamimah before going live.',

    'fields' => [
        'driver' => 'Provider',
        'username' => 'Username',
        'password' => 'Password',
        'sender' => 'Sender ID',
        'sender_helper' => 'The name shown as the sender; it must be registered with Tamimah.',
        'app_id' => 'Application ID',
        'priority' => 'Priority',
        'success_codes' => 'Success status codes',
        'success_codes_helper' => 'Optional, comma separated. Leave empty to count a message as sent when Tamimah reports it processed. Set it once Tamimah tells you which StatusCode values mean success.',
        'endpoint' => 'Service address',
        'endpoint_helper' => 'Leave empty for the standard Tamimah address.',
        'test_phone' => 'Send the test to this phone',
    ],

    'actions' => [
        'save' => 'Save Settings',
        'test' => 'Send test SMS',
        'test_desc' => 'Sends one short message with the settings that are saved now.',
    ],

    'test_message' => 'Test message from :site: SMS is working.',

    'notifications' => [
        'saved' => 'SMS settings saved successfully.',
        'bad_phone' => 'That is not a valid phone number.',
        'test_sent' => 'The test SMS was accepted by the provider.',
        'demo_sent' => 'Demo mode: nothing was sent.',
        'test_failed' => 'The test SMS failed',
    ],
];

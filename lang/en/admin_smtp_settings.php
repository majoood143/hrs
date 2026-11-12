<?php

return [
    'title' => 'SMTP Settings',
    'navigation_label' => 'SMTP Settings',
    'section' => 'Mail server',
    'fields' => [
        'host' => 'SMTP host',
        'port' => 'SMTP port',
        'username' => 'SMTP username',
        'password' => 'SMTP password',
        'encryption' => 'Encryption',
        'encryption_none' => 'None',
        'from_address' => 'From address',
        'from_name' => 'From name',
    ],
    'actions' => [
        'save' => 'Save settings',
        'test' => 'Send a test email',
    ],
    'notifications' => [
        'saved' => 'SMTP settings saved.',
        'test_sent' => 'Test email sent to the "from" address.',
        'test_failed' => 'The test email could not be sent. Check the settings.',
    ],
];

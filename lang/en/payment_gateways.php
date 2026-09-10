<?php

return [
    'title' => 'Payment Gateways',

    'sections' => [
        'active_gateway' => 'Enabled Payment Methods',
        'active_gateway_desc' => 'Choose which payment methods customers can use at checkout.',
        'thawani' => 'Thawani',
        'thawani_desc' => 'Oman-based payment gateway supporting cards and Apple Pay.',
        'nbo' => 'NBO (National Bank of Oman)',
        'nbo_desc' => 'Hosted payment page via the National Bank of Oman.',
        'ccavenue' => 'CCAvenue (Bank Muscat)',
        'ccavenue_desc' => 'Hosted payment page via Bank Muscat / CCAvenue.',
    ],

    'options' => [
        'active_gateway' => 'Payment Methods',
        'free' => 'Free (no payment required)',
        'cash' => 'Cash on arrival',
        'thawani' => 'Thawani',
        'nbo' => 'NBO',
        'ccavenue' => 'CCAvenue',
    ],

    'fields' => [
        'test_mode' => 'Test Mode',
        'thawani_test_mode_helper' => 'Use Thawani\'s sandbox environment instead of live payments.',
        'nbo_test_mode_helper' => 'Use NBO\'s staging environment instead of live payments.',
        'ccavenue_test_mode_helper' => 'Use the CCAvenue test endpoint instead of the live one.',
        'secret_key' => 'Secret Key',
        'publishable_key' => 'Publishable Key',
        'base_url_override' => 'API Base URL Override',
        'endpoint_url_override' => 'Endpoint URL Override',
        'url_override_helper' => 'Leave blank to use the default URL for the selected mode.',
        'ccavenue_endpoint_url_helper' => 'Leave blank to use the default Bank Muscat endpoint for the selected mode.',
        'webhook_secret' => 'Webhook Secret',
        'webhook_secret_helper' => 'Used to verify incoming payment notifications.',
        'tranportal_id' => 'Tranportal ID',
        'tranportal_password' => 'Tranportal Password',
        'resource_key' => 'Resource Key',
        'resource_key_helper' => 'Used to encrypt/decrypt the hosted payment request.',
        'merchant_id' => 'Merchant ID',
        'access_code' => 'Access Code',
        'working_key' => 'Working Key',
        'working_key_helper' => 'Used to encrypt/decrypt the transaction request.',
    ],

    'notifications' => [
        'saved' => 'Payment gateway settings saved successfully.',
    ],

    'actions' => [
        'save' => 'Save Settings',
    ],
];

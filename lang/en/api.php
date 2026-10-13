<?php

// Messages of the mobile app's API (/api/v1). The app shows them as they are.
return [
    'verification' => [
        'invalid_phone' => 'Enter a valid phone number, with the country code if it is not Omani.',
        'throttled' => 'Please wait :seconds seconds before asking for another code.',
        'unavailable' => 'We cannot send codes right now. Please try again later.',
        'wrong' => 'That code is not right. Check it and try again.',
        'expired' => 'This code has expired or was tried too often. Ask for a new one.',
    ],
    'posting' => [
        'verification_required' => 'Confirm your phone number to post.',
        'limit' => 'You can publish up to :count posts a day from the app. Please try again tomorrow.',
    ],
    'app' => [
        'update_optional' => 'A new version of the app is available, with the latest improvements.',
        'update_required' => 'This version of the app is no longer supported. Please update to continue.',
        'maintenance' => 'The app is being updated. Please try again shortly.',
        'feature_disabled' => 'This is not available in the app right now.',
        'announcement_button' => 'Open',
    ],
];

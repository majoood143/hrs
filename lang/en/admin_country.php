<?php

return [
    'navigation' => [
        'label' => 'Country',
        'plural' => 'Countries',
    ],
    'fields' => [
        'country_code' => 'Country code (ISO)',
        'phone_code' => 'Phone country code',
        'flag' => 'Flag',
        'currency_code' => 'Currency code (ISO)',
        'nationality_en' => 'Nationality (English)',
        'nationality_ar' => 'Nationality (Arabic)',
        'order' => 'Order',
        'country_code_helper' => '2-letter ISO code, e.g. OM',
        'phone_code_helper' => 'Digits only, e.g. 968',
        'currency_code_helper' => '3-letter ISO 4217 code, e.g. OMR',
        'is_public' => 'Visible on the public site',
        'is_public_helper' => 'Offered in the public forms (transfer board, stable sign-up) and the mobile app.',
        'public' => 'Public',
    ],
];

<?php

return [
    'title' => 'بوابات الدفع',

    'sections' => [
        'active_gateway' => 'طرق الدفع المفعّلة',
        'active_gateway_desc' => 'اختر طرق الدفع التي يمكن للعملاء استخدامها عند إتمام الحجز.',
        'thawani' => 'ثواني',
        'thawani_desc' => 'بوابة دفع عُمانية تدعم البطاقات و Apple Pay.',
        'nbo' => 'البنك الوطني العماني (NBO)',
        'nbo_desc' => 'صفحة دفع مستضافة عبر البنك الوطني العماني.',
        'ccavenue' => 'CCAvenue (بنك مسقط)',
        'ccavenue_desc' => 'صفحة دفع مستضافة عبر بنك مسقط / CCAvenue.',
    ],

    'options' => [
        'active_gateway' => 'طرق الدفع',
        'free' => 'مجاني (بدون دفع)',
        'cash' => 'الدفع نقدًا عند الوصول',
        'thawani' => 'ثواني',
        'nbo' => 'البنك الوطني العماني',
        'ccavenue' => 'سي سي أفينيو',
    ],

    'fields' => [
        'test_mode' => 'وضع الاختبار',
        'thawani_test_mode_helper' => 'استخدام بيئة الاختبار الخاصة بثواني بدلاً من المدفوعات الفعلية.',
        'nbo_test_mode_helper' => 'استخدام بيئة الاختبار الخاصة بالبنك الوطني العماني بدلاً من المدفوعات الفعلية.',
        'ccavenue_test_mode_helper' => 'استخدام نقطة النهاية الاختبارية لـ CCAvenue بدلاً من النقطة الفعلية.',
        'secret_key' => 'المفتاح السري',
        'publishable_key' => 'المفتاح العلني',
        'base_url_override' => 'تجاوز رابط واجهة برمجة التطبيقات',
        'endpoint_url_override' => 'تجاوز رابط نقطة النهاية',
        'url_override_helper' => 'اتركه فارغًا لاستخدام الرابط الافتراضي للوضع المحدد.',
        'ccavenue_endpoint_url_helper' => 'اتركه فارغًا لاستخدام رابط بنك مسقط الافتراضي للوضع المحدد.',
        'webhook_secret' => 'سر Webhook',
        'webhook_secret_helper' => 'يُستخدم للتحقق من إشعارات الدفع الواردة.',
        'tranportal_id' => 'معرّف Tranportal',
        'tranportal_password' => 'كلمة مرور Tranportal',
        'resource_key' => 'مفتاح المورد',
        'resource_key_helper' => 'يُستخدم لتشفير/فك تشفير طلب الدفع المستضاف.',
        'merchant_id' => 'معرّف التاجر',
        'access_code' => 'رمز الوصول',
        'working_key' => 'مفتاح العمل',
        'working_key_helper' => 'يُستخدم لتشفير/فك تشفير طلب المعاملة.',
    ],

    'notifications' => [
        'saved' => 'تم حفظ إعدادات بوابات الدفع بنجاح.',
    ],

    'actions' => [
        'save' => 'حفظ الإعدادات',
    ],
];

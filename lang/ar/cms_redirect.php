<?php

return [
    'navigation' => [
        'label' => 'إعادة توجيه',
        'plural' => 'إعادات التوجيه',
    ],
    'sections' => [
        'information' => 'معلومات إعادة التوجيه',
    ],
    'fields' => [
        'from_path' => 'من المسار',
        'from_path_helper' => 'المسار القديم، بدون شرطة مائلة في البداية، مثل "old-page".',
        'to_path' => 'إلى المسار',
        'status_code' => 'رمز الحالة',
    ],
    'status_codes' => [
        '301' => '301 — دائم',
        '302' => '302 — مؤقت',
    ],
    'columns' => [
        'from_path' => 'من',
        'to_path' => 'إلى',
        'status_code' => 'الحالة',
        'hits' => 'الزيارات',
    ],
    'actions' => [
        'create_first' => 'أنشئ إعادة توجيه أولى',
    ],
    'empty_state' => [
        'heading' => 'لا توجد إعادات توجيه بعد',
        'description' => 'أعد توجيه الروابط القديمة أو المعطلة إلى وجهة جديدة.',
    ],
];

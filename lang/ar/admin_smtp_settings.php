<?php

return [
    'title' => 'إعدادات SMTP',
    'navigation_label' => 'إعدادات SMTP',
    'section' => 'خادم البريد',
    'fields' => [
        'host' => 'خادم SMTP',
        'port' => 'منفذ SMTP',
        'username' => 'اسم مستخدم SMTP',
        'password' => 'كلمة مرور SMTP',
        'encryption' => 'التشفير',
        'encryption_none' => 'بدون',
        'from_address' => 'عنوان المرسل',
        'from_name' => 'اسم المرسل',
    ],
    'actions' => [
        'save' => 'حفظ الإعدادات',
        'test' => 'إرسال بريد تجريبي',
    ],
    'notifications' => [
        'saved' => 'تم حفظ إعدادات SMTP.',
        'test_sent' => 'تم إرسال البريد التجريبي إلى عنوان المرسل.',
        'test_failed' => 'تعذّر إرسال البريد التجريبي. راجع الإعدادات.',
    ],
];

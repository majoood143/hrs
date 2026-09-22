<?php

return [
    'title' => 'تقرير الدخل والعمولات',

    'filters' => [
        'period' => 'الفترة',
        'date_from' => 'من',
        'date_to' => 'إلى',
        'service' => 'الخدمة',
        'all_services' => 'كل الخدمات',
        'gateway' => 'بوابة الدفع',
        'all_gateways' => 'كل البوابات',
        'language' => 'لغة التحميل',
        'language_helper' => 'لملف PDF و CSV.',
    ],

    'periods' => [
        'today' => 'اليوم',
        'this_week' => 'هذا الأسبوع',
        'this_month' => 'هذا الشهر',
        'last_month' => 'الشهر الماضي',
        'this_year' => 'هذه السنة',
        'custom' => 'فترة مخصصة',
    ],

    'cards' => [
        'collected' => 'المحصّل من العملاء',
        'collected_hint' => ':count طلب مدفوع، كلها في حساب العميل',
        'due_to_us' => 'المبلغ المستحق لنا',
        'due_hint' => 'الرسوم :fee (مع الضريبة) + العمولة :commission',
        'client_keeps' => 'المتبقي لدى العميل',
        'client_hint' => 'المحصّل بعد خصم المسترد والمستحق لنا',
        'refunded' => 'المسترد للعملاء',
        'refunded_hint' => 'رسوم الخدمة لا تُسترد',
    ],

    'sections' => [
        'breakdown' => 'التفصيل',
        'by_gateway' => 'حسب بوابة الدفع',
    ],

    'actions' => [
        'pdf' => 'تحميل الكشف (PDF)',
        'csv' => 'تحميل CSV',
    ],

    'empty' => 'لا توجد طلبات مدفوعة في هذه الفترة.',

    'widgets' => [
        'heading' => 'دخل هذا الشهر',
        'collected' => 'المحصّل',
        'orders' => ':count طلب مدفوع · اليوم :today',
        'due_to_us' => 'المستحق لنا',
        'due_hint' => 'الرسوم + الضريبة :fee · العمولة :commission',
        'client_keeps' => 'المتبقي لدى العميل',
        'client_hint' => 'بعد المسترد وحصتنا',
        'refunded' => 'المسترد',
        'refunded_hint' => 'الرسوم محتفظ بها',
        'chart_heading' => 'الدخل اليومي',
        'last_days' => 'آخر :days يوماً',
    ],
];

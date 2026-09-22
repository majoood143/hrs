<?php

return [
    'navigation' => [
        'label' => 'سجل بوابة الدفع',
        'plural' => 'سجلات بوابات الدفع',
    ],

    'columns' => [
        'created_at' => 'التاريخ',
        'order' => 'الطلب',
        'gateway' => 'البوابة',
        'event' => 'الحدث',
        'result' => 'النتيجة',
        'status_code' => 'حالة HTTP',
        'response_preview' => 'معاينة الاستجابة',
    ],

    'filters' => [
        'gateway' => 'البوابة',
        'event' => 'الحدث',
        'result' => 'النتيجة',
        'status_code' => 'حالة HTTP',
        'date' => 'التاريخ',
        'from' => 'من',
        'to' => 'إلى',
        'payload_search' => 'البحث في البيانات',
        'payload_search_placeholder' => 'مثل: رقم الطلب، رقم التتبع، نص الخطأ...',
    ],

    'events' => [
        'create_session' => 'إنشاء جلسة',
        'get_session' => 'جلب الجلسة',
        'initiate_payment' => 'بدء الدفع',
        'callback' => 'رد البوابة',
        'webhook' => 'Webhook',
        'cancel' => 'إلغاء',
        'amount_mismatch' => 'عدم تطابق المبلغ',
        'late_payment_recovered' => 'استرداد دفعة متأخرة',
        'paid_needs_manual_action' => 'مدفوع ويحتاج إجراءً يدوياً',
    ],

    'outcomes' => [
        'success' => 'نجاح',
        'failed' => 'فشل',
        'pending' => 'قيد الانتظار',
        'error' => 'خطأ',
        'unknown' => 'غير معروف',
    ],

    'status_ranges' => [
        '2xx' => 'نجاح (2xx)',
        '4xx' => 'خطأ من العميل (4xx)',
        '5xx' => 'خطأ من الخادم (5xx)',
        'none' => 'بدون حالة HTTP',
    ],

    'tabs' => [
        'all' => 'الكل',
        'success' => 'نجاح',
        'failed' => 'فشل',
        'error' => 'خطأ',
    ],

    'sections' => [
        'overview' => 'نظرة عامة',
        'payloads' => 'بيانات الطلب والاستجابة',
    ],

    'gateway_logs' => [
        'request' => 'الطلب',
        'response' => 'الاستجابة',
    ],

    'widgets' => [
        'total_last_7_days' => 'المعاملات (7 أيام)',
        'total_desc' => 'استدعاءات بوابة الدفع المسجلة خلال آخر 7 أيام',
        'success_rate' => 'نسبة النجاح',
        'success_desc' => ':count ناجحة',
        'failed' => 'فاشلة',
        'failed_desc' => 'محاولات لم تنتج عنها عملية دفع',
        'errors' => 'الأخطاء',
        'errors_desc' => ':count اليوم',
    ],

    'empty_state' => [
        'heading' => 'لا توجد سجلات لبوابات الدفع بعد',
        'description' => 'ستظهر هنا معاملات ثواني و NBO و CCAvenue فور حدوثها.',
    ],
];

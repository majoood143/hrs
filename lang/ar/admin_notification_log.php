<?php

return [
    'navigation' => ['label' => 'إشعار', 'plural' => 'سجل الإشعارات'],
    'columns' => [
        'created_at' => 'التاريخ',
        'channel' => 'القناة',
        'type' => 'النوع',
        'recipient' => 'المستلم',
        'status' => 'الحالة',
        'order' => 'الطلب',
        'error' => 'الخطأ',
    ],
    'statuses' => ['sent' => 'أُرسلت', 'failed' => 'فشلت', 'skipped' => 'لم تُرسل'],
    'empty' => 'لم تُرسل أي رسائل بعد',
];

<?php

return [
    'navigation' => [
        'label' => 'إعلان',
        'plural' => 'الإعلانات',
    ],
    'fields' => [
        'zone' => 'المنطقة',
        'customer_name' => 'اسم العميل',
        'customer_name_helper' => 'للاستخدام الداخلي فقط — من استأجر هذه المساحة. لا يظهر في الموقع.',
        'media_type' => 'نوع الوسائط',
        'media_type_image' => 'صورة',
        'media_type_video' => 'فيديو',
        'image' => 'صورة',
        'video' => 'فيديو',
        'video_helper' => 'MP4 أو WebM أو MOV، بحد أقصى 20 ميغابايت. يُشغَّل صامتًا ومتكررًا داخل الشريحة.',
        'alt_text' => 'النص البديل',
        'target_url' => 'رابط الانتقال عند النقر',
        'target_url_helper' => 'الوجهة التي ينتقل إليها الزائر عند النقر على الإعلان. اتركه فارغًا لإعلان غير قابل للنقر.',
        'starts_at' => 'تاريخ البدء',
        'ends_at' => 'تاريخ الانتهاء',
        'order' => 'الترتيب',
        'is_active' => 'مفعّل',
        'status' => 'الحالة',
    ],
    'statuses' => [
        'scheduled' => 'مجدول',
        'active' => 'نشط',
        'expired' => 'منتهي',
        'inactive' => 'غير مفعّل',
    ],
    'filters' => [
        'zone' => 'المنطقة',
        'is_active' => 'مفعّل',
    ],
    'empty_state' => [
        'heading' => 'لا توجد إعلانات بعد',
        'description' => 'أنشئ منطقة إعلانية أولاً، ثم أضف الإعلانات المؤجّرة إليها.',
    ],
];

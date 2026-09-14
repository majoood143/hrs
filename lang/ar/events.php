<?php

return [
    'nav_label' => 'الفعاليات',
    'navigation' => [
        'label' => 'فعالية',
        'plural' => 'الفعاليات',
    ],
    'sections' => [
        'details' => 'تفاصيل الفعالية',
        'description' => 'الوصف',
    ],
    'fields' => [
        'title_en' => 'العنوان (إنجليزي)',
        'title_ar' => 'العنوان (عربي)',
        'date' => 'التاريخ',
        'start_time' => 'وقت البدء',
        'end_time' => 'وقت الانتهاء',
        'category' => 'الفئة',
        'link' => 'الرابط',
        'link_helper' => 'مسار داخلي (مثل /blog/my-post) أو رابط خارجي كامل (مثل https://example.com)',
        'description_en' => 'الوصف (إنجليزي)',
        'description_ar' => 'الوصف (عربي)',
    ],
    'uncategorized' => 'غير مصنف',
    'validation' => [
        'link_format' => 'يجب أن يبدأ الرابط بـ / (مسار داخلي) أو http:// أو https:// (رابط خارجي).',
        'end_after_start' => 'يجب أن يكون وقت الانتهاء بعد وقت البدء.',
    ],
    'columns' => [
        'title' => 'العنوان',
        'date' => 'التاريخ',
        'time' => 'الوقت',
        'category' => 'الفئة',
        'link' => 'الرابط',
    ],
    'filters' => [
        'category' => 'الفئة',
    ],
    'empty_state' => [
        'heading' => 'لا توجد فعاليات بعد',
        'description' => 'أنشئ أول فعالية لتظهر في التقويم العام.',
    ],
    'actions' => [
        'create_first' => 'إنشاء أول فعالية',
    ],
    'calendar' => [
        'title' => 'تقويم الفعاليات',
        'subtitle' => 'تصفح الفعاليات القادمة والاجتماعات والمواعيد المهمة.',
        'today' => 'اليوم',
        'search_placeholder' => 'ابحث عن فعالية...',
        'all_categories' => 'الكل',
        'no_events_day' => 'لا توجد فعاليات في هذا اليوم.',
        'more' => ':count المزيد',
        'no_link' => 'لا يوجد رابط مرفق.',
    ],
];

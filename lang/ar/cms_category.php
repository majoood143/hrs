<?php

return [
    'navigation' => [
        'label' => 'تصنيف',
        'plural' => 'التصنيفات',
    ],
    'sections' => [
        'information' => 'معلومات التصنيف',
    ],
    'fields' => [
        'name_en' => 'الاسم (إنجليزي)',
        'name_ar' => 'الاسم (عربي)',
        'slug' => 'الرابط المختصر',
        'description_en' => 'الوصف (إنجليزي)',
        'description_ar' => 'الوصف (عربي)',
        'parent' => 'التصنيف الأب',
        'order' => 'الترتيب',
    ],
    'columns' => [
        'name' => 'الاسم',
        'parent' => 'الأب',
        'posts_count' => 'المقالات',
        'order' => 'الترتيب',
    ],
    'actions' => [
        'create_first' => 'أنشئ تصنيفك الأول',
    ],
    'empty_state' => [
        'heading' => 'لا توجد تصنيفات بعد',
        'description' => 'أنشئ تصنيفات لتنظيم مقالات المدونة.',
    ],
    'notifications' => [
        'cannot_delete' => 'لا يمكن حذف هذا التصنيف',
        'has_posts' => 'لا يزال هذا التصنيف يحتوي على مقالات مرتبطة به.',
    ],
];

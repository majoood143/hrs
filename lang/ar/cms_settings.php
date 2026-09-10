<?php

return [
    'title' => 'إعدادات إدارة المحتوى',

    'tabs' => [
        'general' => 'عام',
        'seo' => 'تحسين محركات البحث',
    ],

    'sections' => [
        'defaults' => 'إعدادات المدونة الافتراضية',
        'defaults_desc' => 'القيم الافتراضية المستخدمة لمقالات المدونة التي لا تحدد قيمها الخاصة.',
        'seo' => 'محركات البحث والتغذيات',
    ],

    'fields' => [
        'default_meta_description' => 'الوصف التعريفي الافتراضي للمدونة',
        'default_meta_description_helper' => 'يُستخدم لمقالات المدونة التي لا تحدد وصفها التعريفي الخاص.',
        'default_og_image' => 'صورة المشاركة الافتراضية للمدونة',
        'default_og_image_helper' => 'تُستخدم لمقالات المدونة التي لا تحدد صورة مشاركة خاصة بها.',
        'posts_per_page' => 'عدد المقالات في الصفحة',
        'sitemap_enabled' => 'تفعيل خريطة الموقع',
        'sitemap_enabled_helper' => 'نشر خريطة موقع XML للصفحات والمقالات على /sitemap.xml.',
        'rss_enabled' => 'تفعيل تغذية RSS',
        'rss_enabled_helper' => 'نشر تغذية RSS لمقالات المدونة.',
        'robots_block_all' => 'حظر جميع محركات البحث',
        'robots_block_all_helper' => 'إضافة توجيه noindex على مستوى الموقع بالكامل. يُستخدم فقط أثناء التجربة أو الصيانة.',
    ],

    'notifications' => [
        'updated' => 'تم تحديث إعدادات إدارة المحتوى بنجاح.',
    ],

    'actions' => [
        'save' => 'حفظ الإعدادات',
    ],
];

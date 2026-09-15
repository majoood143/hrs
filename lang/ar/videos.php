<?php

return [
    'navigation' => [
        'label' => 'فيديو',
        'plural' => 'الفيديوهات',
    ],
    'fields' => [
        'folder' => 'المجلد',
        'title' => 'العنوان',
        'slug' => 'الرابط المختصر',
        'description' => 'الوصف',
        'youtube_url' => 'رابط يوتيوب',
        'youtube_url_helper' => 'الصق رابط يوتيوب (مثل youtube.com/watch?v=... أو youtu.be/...).',
        'order' => 'الترتيب',
        'is_active' => 'مفعّل',
    ],
    'filters' => [
        'folder' => 'المجلد',
    ],
    'validation' => [
        'youtube_url' => 'أدخل رابط فيديو يوتيوب صحيحاً.',
    ],
    'empty_state' => [
        'heading' => 'لا توجد فيديوهات بعد',
        'description' => 'أنشئ مجلد فيديو أولاً، ثم أضف الفيديوهات إليه.',
    ],
    'library' => [
        'eyebrow' => 'مكتبة الفيديو',
        'title' => 'مكتبة الفيديو',
        'subtitle' => 'شاهد أبرز اللحظات وإعادة العروض والمحتوى من كواليس كل موسم.',
        'no_results_title' => 'لا توجد فيديوهات بعد',
        'no_results_body' => 'تابعنا قريباً لمشاهدة فيديوهات جديدة.',
        'view_all' => 'مشاهدة جميع الفيديوهات',
    ],
    'back_to_library' => 'العودة إلى مكتبة الفيديو',
];

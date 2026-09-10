<?php

return [
    'navigation' => [
        'label' => 'شريك',
        'plural' => 'الشركاء',
    ],
    'sections' => [
        'information' => 'معلومات الشريك',
    ],
    'fields' => [
        'name_en' => 'الاسم (إنجليزي)',
        'name_ar' => 'الاسم (عربي)',
        'logo' => 'الشعار',
        'website_url' => 'رابط الموقع',
        'description_en' => 'الوصف (إنجليزي)',
        'description_ar' => 'الوصف (عربي)',
        'type' => 'نوع الشريك',
        'is_active' => 'نشط',
        'order' => 'الترتيب',
    ],
    'types' => [
        'stable' => 'إسطبل / مزرعة',
        'transport' => 'شركة نقل',
        'veterinary' => 'بيطري',
        'authority' => 'جهة تسجيل',
    ],
    'columns' => [
        'logo' => 'الشعار',
        'name' => 'الاسم',
        'type' => 'النوع',
        'is_active' => 'نشط',
        'order' => 'الترتيب',
    ],
    'filters' => [
        'type' => 'النوع',
    ],
    'actions' => [
        'create_first' => 'أضف أول شريك',
    ],
    'empty_state' => [
        'heading' => 'لا يوجد شركاء بعد',
        'description' => 'أضف الإسطبلات والشركات التي تتعاون معها.',
    ],
];

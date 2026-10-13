<?php

return [
    'navigation' => [
        'label' => 'مرفق',
        'plural' => 'المرفقات',
    ],
    'fields' => [
        'name' => 'الاسم',
        'description' => 'الوصف',
        'file' => 'الملف',
        'file_helper' => 'ملف PDF أو صورة (JPEG أو PNG أو WebP)، حتى 10 ميغابايت. يُحفظ بشكل خاص: لا يفتحه إلا المشرفون.',
        'type' => 'النوع',
        'horse' => 'الحصان',
        'uploaded_by' => 'رفعه',
        'is_visible_to_client' => 'ظاهر للمالك',
        'is_active' => 'نشط',
        'is_approved' => 'معتمد',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'آخر تحديث',
        'open' => 'فتح',
    ],
    'types' => [
        'id_card' => 'البطاقة الشخصية',
        'cr_card' => 'السجل التجاري',
        'passport' => 'جواز السفر',
        'front_side' => 'صورة أمامية',
        'left_side' => 'صورة الجانب الأيسر',
        'right_side' => 'صورة الجانب الأيمن',
        'back_side' => 'صورة خلفية',
        'DNA' => 'تقرير الحمض النووي',
        'other' => 'أخرى',
    ],
];

<?php

// رسائل واتساب المرسلة إلى مشرفي الموقع. تُرسل كل رسالة بجميع لغات الموقع معًا
// (App\Services\WhatsApp\BilingualMessage)، لذا حافظ على تطابقها مع lang/en/whatsapp.php.
return [
    'new_post' => [
        'heading' => '🆕 إعلان جديد على :site',
        'type' => 'القسم: :type',
        'contact' => '📞 للتواصل: :contact',
        'view' => '🔗 على الموقع: :link',
        'admin' => '🛠 في لوحة التحكم: :link',
    ],

    'new_stable' => [
        'heading' => '🐎 إسطبل جديد مسجّل على :site بانتظار الموافقة',
        'place' => '📍 :city، :region',
        'owner' => '👤 المالك: :name (:phone)',
        'admin' => '🛠 للمراجعة والموافقة: :link',
    ],

    'post_types' => [
        'transfer_post' => 'لوحة النقل',
        'horse_sale_post' => 'خيول للبيع',
        'tool_sale_post' => 'أدوات للبيع',
        'farrier' => 'البياطرة',
        'stable_registration' => 'تسجيل إسطبلات جديدة',
    ],

    'transfer_kinds' => [
        'offer' => 'يعرض النقل',
        'request' => 'يبحث عن نقل',
    ],

    'fields' => [
        'name' => 'الاسم: :value',
        'kind' => 'الإعلان: :value',
        'route' => 'من :from إلى :to',
        'date' => 'التاريخ: :value',
        'capacity' => 'السعة: :value',
        'category' => 'الفئة: :value',
        'price' => 'السعر: :value',
        'negotiable' => '(قابل للتفاوض)',
    ],

    'test' => '✅ رسالة تجريبية من :site: تنبيهات المشرفين عبر واتساب تعمل.',
];

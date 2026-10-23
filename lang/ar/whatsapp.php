<?php

// رسائل واتساب المرسلة إلى مشرفي الموقع. تُرسل كل رسالة بجميع لغات الموقع معًا
// (App\Services\WhatsApp\BilingualMessage)، لذا حافظ على تطابقها مع lang/en/whatsapp.php.
return [
    'new_post' => [
        'heading' => '🆕 إعلان جديد على :site',
        'heading_pending' => '⏳ إعلان جديد على :site بانتظار موافقتك',
        'type' => 'القسم: :type',
        'contact' => '📞 للتواصل: :contact',
        'view' => '🔗 على الموقع: :link',
        'admin' => '🛠 في لوحة التحكم: :link',
        'approve' => '✅ للموافقة عليه من لوحة التحكم: :link',
    ],

    'new_stable' => [
        'heading' => '🐎 إسطبل جديد مسجّل على :site بانتظار الموافقة',
        'place' => '📍 :city، :region',
        'owner' => '👤 المالك: :name (:phone)',
        'admin' => '🛠 للمراجعة والموافقة: :link',
    ],

    'youtube' => [
        'heading' => '▶️ جديد على يوتيوب: :channel',
        'kind' => 'النوع: :kind',
        'live' => '🔴 بث مباشر الآن',
        'upcoming' => '📅 بث مباشر أو عرض أول مجدول',
        'upcoming_at' => '📅 بث مباشر أو عرض أول يبدأ :time (بتوقيت مسقط)',
        'not_embeddable' => '⚠️ القناة لا تسمح بتشغيله على مواقع أخرى، لذا لا يمكن تشغيله في مكتبة الفيديو.',
        'more' => '…و:count غيرها في صندوق الوارد.',
        'added' => '📚 أُضيف إلى مكتبة الفيديو وهو ظاهر: :link',
        'added_hidden' => '📚 أُضيف إلى مكتبة الفيديو مخفيًا حتى تنشره: :link',
        'inbox' => '🛠 للإضافة إلى مكتبة الفيديو: :link',
    ],

    'race_result' => [
        'heading' => 'نتائج السباق: :race',
        'jockey' => 'الفارس: :name',
        'trainer' => 'المدرب: :name',
        'owner' => 'المالك: :name',
        'margin' => 'الفارق :value',
        'replay' => 'إعادة السباق: :link',
        'no_replay' => 'إعادة السباق: لم تُنشر على يوتيوب بعد',
        'page' => 'النتائج كاملة: :link',
        'video_heading' => 'إعادة السباق متاحة على يوتيوب: :race',
    ],

    'post_types' => [
        'transfer_post' => 'لوحة النقل',
        'horse_sale_post' => 'خيول للبيع',
        'tool_sale_post' => 'أدوات للبيع',
        'farrier' => 'البياطرة',
        'stable_registration' => 'تسجيل إسطبلات جديدة',
        'youtube_video' => 'فيديوهات يوتيوب جديدة (القنوات المتابعة)',
        'race_result' => 'نتائج السباقات (تُرسل تلقائيًا عند نشرها)',
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

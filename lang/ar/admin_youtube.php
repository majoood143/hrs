<?php

// لوحة التحكم ← قنوات يوتيوب / وارد يوتيوب (القنوات المتابعة؛ App\Services\YouTube). حافظ على
// تطابقها مع lang/en/admin_youtube.php.
return [
    'channels' => [
        'label' => 'قناة يوتيوب',
        'plural' => 'قنوات يوتيوب',
        'intro' => 'قنوات عامة نتابعها (لا يلزم أن تكون لنا). كل 15 دقيقة تصل الفيديوهات الجديدة إلى وارد يوتيوب ويصل المشرفين تنبيه عبر واتساب (الإعدادات العامة ← واتساب).',
        'empty_heading' => 'لا توجد قنوات متابعة بعد',
        'empty_description' => 'أضف قناة يوتيوب برابطها، مثل youtube.com/@name.',
    ],

    'inbox' => [
        'label' => 'فيديو يوتيوب',
        'plural' => 'وارد يوتيوب',
        'empty_heading' => 'لا جديد',
        'empty_description' => 'تظهر هنا الفيديوهات الجديدة من القنوات المتابعة.',
    ],

    'fields' => [
        'url' => 'رابط القناة',
        'url_helper' => 'رابط بصيغة youtube.com/@name أو youtube.com/channel/UC…، أو المعرّف ‎@name.',
        'channel' => 'القناة',
        'channel_id' => 'معرّف القناة',
        'folder' => 'مجلد الفيديو الافتراضي',
        'folder_helper' => 'يُختار تلقائيًا عند إضافة فيديو من هذه القناة إلى المكتبة.',
        'skip_shorts' => 'تجاهل المقاطع القصيرة (Shorts)',
        'skip_shorts_helper' => 'لا تنبيه ولا إدخال في الوارد للمقاطع القصيرة.',
        'is_active' => 'قيد المتابعة',
        'checked_at' => 'آخر فحص',
        'never_checked' => 'لم تُفحص بعد',
        'last_error' => 'مشكلة',
        'waiting' => 'جديد',
        'title' => 'العنوان',
        'kind' => 'النوع',
        'published_at' => 'تاريخ النشر',
        'scheduled_at' => 'يبدأ',
        'duration' => 'المدة',
        'embeddable' => 'يُشغَّل على مواقع أخرى',
        'status' => 'الحالة',
        'notified_at' => 'أُرسل التنبيه',
    ],

    'kinds' => [
        'video' => 'فيديو',
        'short' => 'مقطع قصير',
        'live' => 'بث مباشر الآن',
        'upcoming' => 'بث مباشر أو عرض أول مجدول',
        'stream' => 'بث مباشر (انتهى)',
    ],

    'statuses' => [
        'new' => 'جديد',
        'added' => 'في المكتبة',
        'ignored' => 'متجاهَل',
        'skipped' => 'متخطّى (مقطع قصير)',
    ],

    'actions' => [
        'check_now' => 'افحص الآن',
        'open_youtube' => 'افتح في يوتيوب',
        'add_to_library' => 'أضف إلى المكتبة',
        'open_in_library' => 'افتح في المكتبة',
        'ignore' => 'تجاهل',
        'restore' => 'أعده إلى الجديد',
        'check_all' => 'افحص كل القنوات الآن',
    ],

    'notifications' => [
        'added' => 'تتم الآن متابعة :channel',
        'added_body' => 'أحدث الفيديوهات (:count) في وارد يوتيوب. سيصلك تنبيه واتساب بالفيديوهات الجديدة.',
        'checked' => 'تم فحص :channel',
        'checked_body' => 'جديد: :found، تنبيهات: :alerted.',
        'check_failed' => 'تعذّر فحص :channel',
        'checked_all' => 'تم فحص القنوات',
        'checked_all_body' => 'فيديوهات جديدة: :found.',
        'ignored' => 'تم التجاهل',
        'restored' => 'أُعيد إلى الجديد',
    ],

    'validation' => [
        'duplicate' => 'هذه القناة متابعة بالفعل.',
    ],

    'no_key' => 'لم يُضبط مفتاح YouTube API (‏YOUTUBE_API_KEY في ‎.env): ستُكتشف الفيديوهات الجديدة، ولكن دون حالة البث أو موعده أو المدة أو إمكانية التضمين.',

    'errors' => [
        'not_found' => 'يوتيوب لا يعرف هذه القناة.',
        'unreachable' => 'تعذّر الوصول إلى يوتيوب.',
        'feed_failed' => 'أعادت خلاصة القناة خطأ (:detail).',
        'bad_feed' => 'تعذّرت قراءة خلاصة القناة.',
        'api_failed' => 'رفضت واجهة يوتيوب الطلب (:detail).',
        'unknown' => 'حدث خطأ (:detail).',
    ],
];

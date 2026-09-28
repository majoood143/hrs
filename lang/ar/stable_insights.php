<?php

// مؤشرات حجوزات الإسطبلات: صفحة المؤشرات للمالك، وصفحة مؤشرات الإسطبلات للمشرفين، وعنصر لوحة
// التحكم. حافظ على تطابقها مع lang/en/stable_insights.php.
return [
    'title' => 'المؤشرات',
    'admin_title' => 'مؤشرات الإسطبلات',
    'all_stables' => 'كل الإسطبلات',
    'export' => 'ترتيب الإسطبلات (CSV)',

    'kpi' => [
        'sales' => 'المبيعات',
        'stable_share' => 'حصتك',
        'our_share' => 'أرباحنا',
        'commission' => 'العمولة',
        'bookings' => 'الحجوزات',
        'occupancy' => 'الأماكن المباعة',
        'occupancy_hint' => 'من الأماكن المتاحة في الحصص التي أقيمت',
        'customers' => 'العملاء',
        'customers_hint' => ':new جديد · :returning عائد',
        'cancellations' => 'الإلغاء',
        'no_show' => 'عدم الحضور: :rate',
        'rating' => 'التقييم',
        'vs_previous' => ':delta مقارنة بالفترة السابقة',
        'no_compare' => 'لا توجد فترة سابقة للمقارنة',
    ],

    'chart' => [
        'heading_day' => 'المبيعات حسب اليوم',
        'heading_week' => 'المبيعات حسب الأسبوع',
        'heading_month' => 'المبيعات حسب الشهر',
        'description_owner' => 'ما دفعه العملاء للإسطبل مقسّمًا إلى حصتك والعمولة (دون رسوم الخدمة).',
        'description_admin' => 'حصة الإسطبلات وأرباحنا (رسوم الخدمة والعمولة).',
        'stable_share' => 'حصتك',
        'commission' => 'العمولة',
        'stables_share' => 'حصة الإسطبلات',
        'our_share' => 'أرباحنا',
    ],

    'occupancy' => [
        'heading' => 'متى تُباع الأماكن',
        'description' => 'الأماكن المباعة من الأماكن المتاحة، حسب اليوم ووقت البدء، في الحصص التي أقيمت.',
        'day' => 'اليوم',
        'cell' => ':day :time: :booked من :capacity أماكن (:pct%)',
        'overall' => 'الإجمالي :pct% (:booked من :capacity أماكن)',
        'empty' => 'لم تُقَم حصص في هذه الفترة بعد.',
    ],

    'services' => [
        'heading' => 'الأكثر مبيعًا',
        'name' => 'الخدمة أو الباقة',
        'sold' => 'المبيع',
        'empty' => 'لم يُبع شيء في هذه الفترة.',
    ],

    'stables' => [
        'heading' => 'الإسطبلات',
        'description' => 'الأكثر مبيعًا أولًا. أرباحنا هي رسوم الخدمة والعمولة.',
    ],

    'dashboard' => [
        'heading' => 'حجوزات الإسطبلات هذا الشهر',
        'sales' => 'المبيعات :amount',
        'waiting' => 'بانتظار المشرف',
        'waiting_hint' => ':stables إسطبلات للموافقة · :keys مفاتيح بوابات للمراجعة',
    ],
];

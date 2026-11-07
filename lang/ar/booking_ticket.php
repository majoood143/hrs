<?php

return [
    'title' => 'تذكرة الحجز',
    'filename' => 'ticket',
    'download' => 'تحميل التذكرة (PDF)',
    'qr_label' => 'رمز QR للحجز :reference',
    'service' => 'الحصة',
    'date' => 'التاريخ',
    'time' => 'الوقت',
    // "من … إلى …" in Arabic: two bare times side by side come out reversed in a right-to-left PDF
    'time_range' => 'من :start إلى :end',
    'riders' => 'الفرسان',
    'trainer' => 'المدرب',
    'customer' => 'باسم',
    'payment' => 'الدفع',
    'location' => 'الموقع',
    'map' => 'افتح الخريطة',
    'mail_attached' => 'التذكرة مرفقة (PDF): اعرض رمز QR في الإسطبل لتسجيل الحضور.',
    'show_qr' => 'اعرض هذا الرمز في الإسطبل لتسجيل الحضور.',
    'status' => [
        'confirmed' => 'مؤكد',
        'completed' => 'تم تسجيل الحضور',
    ],

    'landing' => [
        'title' => 'الحجز :reference',
        'intro' => 'هذه تذكرة حجز في :stable.',
        'staff' => 'لفريق الإسطبل: تسجيل الحضور',
        'customer' => 'هل هذا حجزك؟ تجده في حسابك.',
    ],

    'check_in' => [
        'title' => 'تسجيل الحضور',
        'reference' => 'رقم الحجز',
        'reference_placeholder' => 'مثال: BK-7Q2M9XKD، أو الصق رابط التذكرة',
        'reference_hint' => 'امسح رمز QR في تذكرة العميل بكاميرا هاتفك، أو اكتب رقم الحجز المطبوع تحته.',
        'find' => 'ابحث عن الحجز',
        'not_found' => 'لا يوجد حجز :reference في هذا الإسطبل.',
        'other_stable' => 'هذا الحجز في :stable.',
        'open_other' => 'سجّل الحضور في :stable',
        'ready' => 'جاهز لتسجيل الحضور',
        'collect' => 'استلم :total قبل الحصة (الدفع في الإسطبل).',
        'not_yet' => 'هذا الحجز بتاريخ :date: يمكن تسجيل الحضور من ذلك اليوم.',
        'already' => 'تم تسجيل الحضور (:time).',
        'no_show' => 'سُجّل عدم الحضور. سجّل الحضور لتصحيحه.',
        'pending' => 'غير مؤكد بعد: لم يدفع العميل.',
        'cancelled' => 'أُلغي هذا الحجز.',
        'check_in' => 'تسجيل الحضور',
        'done' => 'تم تسجيل الحضور: حصة ممتعة!',
        'another' => 'تسجيل حضور آخر',
    ],
];

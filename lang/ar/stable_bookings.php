<?php

// حجوزات الإسطبلات على الموقع (البحث، صفحة الخدمة، نموذج الحجز، بطاقة الطلب) والرسائل عن الحجز
// إلى العميل والإسطبل. حافظ على تطابقها مع lang/en/stable_bookings.php.
return [
    'book' => 'احجز',
    'price' => 'السعر',
    'per_rider' => 'للفارس',
    'free' => 'مجانًا',
    'private' => 'حصة خاصة',
    'group_of' => '{1} حتى فارس واحد|{2} حتى فارسين|[3,10] حتى :count فرسان|[11,*] حتى :count فارسًا',
    'riders_count' => '{1} فارس واحد|{2} فارسان|[3,10] :count فرسان|[11,*] :count فارسًا',
    'places_left' => '{1} بقي مكان واحد|{2} بقي مكانان|[3,10] بقيت :count أماكن|[11,*] بقي :count مكانًا',
    'with_trainer' => 'مع :name',
    'directions' => 'الاتجاهات',
    'update' => 'تحديث',

    'search' => [
        'eyebrow' => 'احجز موعدًا',
        'title' => 'احجز حصص ركوب الخيل',
        'intro' => 'ابحث عن المواعيد المتاحة في الإسطبلات القريبة منك، واختر الوقت واحجز خلال دقيقة.',
        'date' => 'التاريخ',
        'riders' => 'الفرسان',
        'time' => 'وقت اليوم',
        'any_time' => 'أي وقت',
        'times' => [
            'morning' => 'صباحًا (قبل 12:00)',
            'afternoon' => 'بعد الظهر (12:00–17:00)',
            'evening' => 'مساءً (من 17:00)',
        ],
        'button' => 'بحث',
        'week' => 'الأيام السبعة القادمة',
        'times_count' => '{0} ممتلئ|{1} موعد واحد|{2} موعدان|[3,10] :count مواعيد|[11,*] :count موعدًا',
        'none_title' => 'لا توجد مواعيد متاحة في هذا اليوم',
        'none_body' => 'جرّب يومًا آخر، أو عددًا أقل من الفرسان، أو منطقة أوسع.',
        'next_date' => 'أقرب موعد متاح: :date',
    ],

    'stable_page' => [
        'heading' => 'احجز حصة هنا',
        'pick_time' => 'اختر موعدًا',
    ],

    'offering' => [
        'pick_day' => 'اختر اليوم',
        'no_days' => 'لا توجد مواعيد متاحة حاليًا. يرجى المحاولة لاحقًا.',
        'day_full' => 'كل مواعيد هذا اليوم محجوزة. يرجى اختيار يوم آخر.',
    ],

    'form' => [
        'eyebrow' => 'حجزك',
        'title' => 'أكمل حجزك',
        'change_time' => 'اختر موعدًا آخر',
        'rider' => 'الفارس',
        'rider_n' => 'الفارس :n',
        'contact' => 'كيف نتواصل معك',
        'your_name' => 'اسمك',
        'email' => 'البريد الإلكتروني (اختياري)',
        'phone_hint' => 'يصلك التأكيد برسالة نصية. سجّل الدخول بهذا الرقم لاحقًا لعرض الحجز أو إلغائه.',
        'payment' => 'طريقة الدفع',
        'waiver' => 'إقرار إخلاء المسؤولية',
        'waiver_accept' => 'قرأت الإقرار وأوافق عليه عن كل فارس في هذا الحجز.',
        'summary' => 'الملخص',
        'submit' => 'تأكيد الحجز',
        'booked' => 'تم تأكيد حجزك، وأرسلنا لك التفاصيل برسالة نصية.',
        'check_errors' => 'يرجى مراجعة البيانات أدناه.',
    ],

    'rider' => [
        'name' => 'الاسم الكامل',
        'age' => 'العمر',
        'level' => 'مستوى الركوب',
        'weight' => 'الوزن (كجم)',
        'height' => 'الطول (سم)',
        'guardian_name' => 'اسم ولي الأمر',
        'guardian_phone' => 'هاتف ولي الأمر',
        'notes' => 'ملاحظات للمدرب (الصحة، الخبرة…)',
    ],

    'levels' => [
        'beginner' => 'مبتدئ',
        'intermediate' => 'متوسط',
        'advanced' => 'متقدم',
    ],

    'payment_options' => [
        'online' => 'ادفع إلكترونيًا الآن',
        'online_hint' => 'بالبطاقة عبر صفحة الدفع الآمنة. تُؤكَّد أماكنك فور الدفع.',
        'at_stable' => 'الدفع في الإسطبل',
        'at_stable_hint' => 'تُؤكَّد أماكنك الآن، وتدفع للإسطبل يوم الموعد.',
    ],

    'policy' => [
        'cancel_until' => 'الإلغاء المجاني عبر الموقع حتى :hours ساعة قبل الموعد. رسوم الخدمة غير مستردة.',
        'no_online_cancel' => 'للتعديل أو الإلغاء، تواصل مع الإسطبل.',
    ],

    'card' => [
        'eyebrow' => 'الحجز',
        'pay_at_stable' => 'ادفع :total في الإسطبل يوم الموعد.',
        'cancelled_by_stable' => 'ألغى الإسطبل هذا الموعد.',
        'cancel' => 'إلغاء الحجز',
        'cancel_confirm' => 'هل تريد إلغاء هذا الحجز؟ لا يمكن التراجع عن ذلك.',
        'cancelled' => 'تم إلغاء حجزك.',
        'sign_in_to_cancel' => 'سجّل الدخول برقم هاتفك للإلغاء',
    ],

    'errors' => [
        'no_riders' => 'يرجى إضافة فارس واحد على الأقل.',
        'slot_gone' => 'هذا الموعد لم يعد متاحًا.',
        'not_bookable' => 'لا يمكن حجز هذا الموعد حاليًا.',
        'closed' => 'أُغلق الحجز لهذا الموعد.',
        'riders_range' => 'تقبل هذه الحصة من :min إلى :max فرسان في الحجز الواحد.',
        'only_left' => '{1} بقي مكان واحد فقط في هذا الموعد.|{2} بقي مكانان فقط في هذا الموعد.|[3,10] بقيت :count أماكن فقط في هذا الموعد.|[11,*] بقي :count مكانًا فقط في هذا الموعد.',
        'full' => 'هذا الموعد محجوز بالكامل.',
        'payment_option' => 'يرجى اختيار طريقة الدفع.',
        'cannot_cancel' => 'لم يعد بالإمكان إلغاء هذا الحجز عبر الموقع. يرجى التواصل مع الإسطبل.',
        'not_confirmed' => 'لا يمكن تسجيل ذلك إلا لحجز مؤكد.',
    ],

    'fields' => [
        'reference' => 'الحجز',
        'session' => 'الحصة',
        'stable' => 'الإسطبل',
        'date' => 'التاريخ',
        'time' => 'الوقت',
        'riders' => 'الفرسان',
        'customer' => 'العميل',
        'payment' => 'الدفع',
        'reason' => 'السبب',
    ],

    'payment' => [
        'paid_online' => 'مدفوع إلكترونيًا',
        'pending' => 'بانتظار الدفع',
        'at_stable' => 'يُدفع في الإسطبل: :total',
        'free' => 'مجاني',
        'package' => 'مدفوع من الباقة :reference',
    ],

    'cancelled_by' => [
        'customer' => 'العميل',
        'stable' => 'الإسطبل',
        'expired' => 'النظام (لم يُدفع)',
    ],

    'sms' => [
        'reminder' => ':site: تذكير: :offering في :stable، :date :time، :riders. التفاصيل: :url',
        'reminder_map' => ':site: تذكير: :offering في :stable، :date :time، :riders. الاتجاهات: :map التفاصيل: :url',
        'customer_confirmed' => ':site: تم تأكيد الحجز :reference. :offering في :stable، :date :time، :riders. :payment. التفاصيل: :url',
        'customer_cancelled_customer' => ':site: تم إلغاء الحجز :reference (:date :time). التفاصيل: :url',
        'customer_cancelled_stable' => ':site: ألغى :stable حجزك :reference (:date :time). :reason التفاصيل: :url',
    ],

    'alert' => [
        'confirmed' => 'حجز جديد :reference: :offering، :date :time، :riders (:names). العميل: :customer :phone. :payment. :panel',
        'cancelled' => 'أُلغي الحجز :reference (:offering، :date :time، :riders) من قِبل :who.',
        'refund_note' => 'كان مدفوعًا: يجب استرداد المبلغ.',
    ],

    'mail' => [
        'customer_button' => 'عرض حجزي',
        'stable_button' => 'افتح حجوزاتك',
        'customer_refund' => 'سيُعاد إليك المبلغ المدفوع (رسوم الخدمة غير مستردة)، وسنبلغك عند إتمام ذلك.',
        'stable_refund' => 'كان هذا الحجز مدفوعًا، لذا يجب استرداد المبلغ.',
        'customer_confirmed' => [
            'subject' => 'تم تأكيد الحجز :reference: :offering يوم :date',
            'heading' => 'تم تأكيد حجزك',
            'intro' => 'شكرًا لك. تم حجز أماكنك في :stable.',
        ],
        'customer_cancelled' => [
            'subject' => 'تم إلغاء الحجز :reference',
            'heading' => 'تم إلغاء حجزك',
            'intro' => 'أُلغي حجزك في :stable من قِبل :who.',
        ],
        'stable_confirmed' => [
            'subject' => 'حجز جديد :reference: :date :time',
            'heading' => 'حجز جديد',
            'intro' => 'حجز :customer :offering.',
        ],
        'stable_cancelled' => [
            'subject' => 'تم إلغاء الحجز :reference',
            'heading' => 'أُلغي حجز',
            'intro' => 'أُلغي هذا الحجز من قِبل :who.',
        ],
    ],
];

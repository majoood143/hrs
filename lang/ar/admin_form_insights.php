<?php

return [
    'action' => 'الإحصاءات',
    'title' => 'إحصاءات: :form',
    'breadcrumb' => 'الإحصاءات',

    'filters' => [
        'period' => 'الفترة',
        'date_from' => 'من',
        'date_to' => 'إلى',
        'order_status' => 'حالة الطلب',
        'all_statuses' => 'كل الحالات',
        'order_status_helper' => 'الردود التي لطلبها هذه الحالة فقط.',
        'language' => 'لغة التنزيل',
        'language_helper' => 'لغة ملف PDF و CSV.',
    ],

    'periods' => [
        'all' => 'كل الفترات',
        'today' => 'اليوم',
        'this_week' => 'هذا الأسبوع',
        'this_month' => 'هذا الشهر',
        'last_month' => 'الشهر الماضي',
        'this_year' => 'هذه السنة',
        'custom' => 'تواريخ مخصصة',
    ],

    'cards' => [
        'submissions' => 'الردود',
        'vs_previous' => ':delta مقارنة بالفترة السابقة (:previous)',
        'all_time' => 'منذ أول رد',
        'unread' => 'غير مقروءة',
        'unread_hint' => 'ردود هذه الفترة التي لم يفتحها أحد بعد.',
        'last_submission' => 'آخر رد',
        'never' => 'لا يوجد بعد',
    ],

    'sections' => [
        'over_time' => 'الردود عبر الزمن',
        'over_time_by_day' => 'يوميًا',
        'over_time_by_week' => 'أسبوعيًا',
        'over_time_by_month' => 'شهريًا',
        'orders_by_status' => 'الطلبات حسب الحالة',
        'orders_by_status_hint' => 'الطلبات التي أُنشئت من هذا النموذج في الفترة.',
        'money' => 'المبالغ',
        'money_hint' => 'طلبات هذا النموذج المدفوعة في الفترة، حسب تاريخ الدفع (الأرقام نفسها في تقرير الإيرادات).',
    ],

    'money' => [
        'due_hint' => 'الرسوم + الضريبة :fee، العمولة :commission',
        'open_report' => 'فتح تقرير الإيرادات',
    ],

    'kinds' => [
        'choices' => 'إجابة واحدة',
        'multi' => 'يسمح بعدة إجابات',
        'boolean' => 'مربع اختيار',
        'nationality' => 'الجنسية',
        'number' => 'رقم',
        'date' => 'تاريخ',
    ],

    'field' => [
        'answered' => 'أجاب :answered من :total (:type)',
        'no_answers' => 'لم يجب أحد عن هذا الحقل في هذه الفترة.',
    ],

    'answers' => [
        'ticked' => 'تم الاختيار',
        'not_ticked' => 'لم يتم الاختيار',
    ],

    'stats' => [
        'min' => 'الأدنى',
        'mean' => 'المتوسط',
        'median' => 'الوسيط',
        'max' => 'الأعلى',
    ],

    'empty' => 'لا توجد ردود في هذه الفترة.',
    'no_chartable' => 'لا يحتوي هذا النموذج على حقول ذات إجابات محددة لعرضها في رسم بياني (خيارات، مربعات اختيار، جنسية، أرقام، تواريخ). النصوص الحرة والبريد والهواتف والملفات لا تُعرض أبدًا.',

    'actions' => [
        'pdf' => 'PDF',
        'csv' => 'CSV',
        'edit_form' => 'تعديل النموذج',
    ],

    'export' => [
        'title' => 'إحصاءات النموذج',
        'period' => 'الفترة',
        'previous_period' => 'الفترة السابقة',
    ],
];

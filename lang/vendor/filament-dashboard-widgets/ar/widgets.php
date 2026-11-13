<?php

declare(strict_types=1);

// The package ships English and French only.
return [

    'actions' => [
        'view_all' => 'عرض الكل',
        'view_more' => 'عرض المزيد',
    ],

    'empty_state' => [
        'heading' => 'لا توجد بيانات',
        'description' => 'لا يوجد شيء لعرضه بعد.',
    ],

    'metric' => [
        'trend' => [
            'up' => 'اتجاه صاعد',
            'down' => 'اتجاه هابط',
            'neutral' => 'لا تغيير يُذكر',
        ],
        'comparison' => 'مقارنة بالفترة السابقة',
    ],

    'goal' => [
        'remaining' => 'المتبقي',
        'reached' => 'تم بلوغ الهدف',
        'exceeded' => 'تجاوز الهدف بـ :value',
        'of_target' => ':current من :target',
        'deadline' => 'الموعد النهائي: :date',
        'overdue' => 'متأخر',
        'progress' => 'تقدّم :label: :percentage',
    ],

    'breakdown' => [
        'total' => 'الإجمالي',
        'share' => ':label: :value (:percentage من الإجمالي)',
    ],

    'detail' => [
        'placeholder' => '-',
    ],

    'bullet' => [
        'target' => 'الهدف: :value',
        'benchmark' => 'المرجع: :value',
        'status' => [
            'below' => 'دون الهدف',
            'met' => 'ضمن الهدف',
            'above' => 'فوق الهدف',
        ],
    ],

    'trend' => [
        'comparison' => 'مقارنة بالفترة السابقة',
        'summary' => 'رسم بياني لـ :label. القيمة الحالية: :value.',
    ],

    'composition' => [
        'total' => 'الإجمالي: :value',
    ],

    'funnel' => [
        'overall' => 'التحويل الإجمالي: :percentage',
    ],

    'timeline' => [
        'today' => 'اليوم',
        'yesterday' => 'أمس',
    ],

];

<?php

return [
    'title' => 'نصوص الإشعارات',

    'groups' => [
        'sms' => 'الرسائل النصية',
        'sms_desc' => 'رسالة قصيرة لكل حالة. يجب أن يبقى رقم الطلب والرابط والمبلغ في النص.',
        'received' => 'البريد: استلام الطلب أو دفعه',
        'received_desc' => 'يُرسل عند وصول الطلب (مع الإيصال مرفقاً إن كان مدفوعاً).',
        'completed' => 'البريد: اكتمال الطلب',
        'completed_desc' => 'يُرسل عندما يكمل المشرف الطلب.',
        'rejected' => 'البريد: رفض الطلب',
        'rejected_desc' => 'يُرسل عندما يرفض المراجع الطلب. يُضاف سبب المراجع تلقائياً.',
        'refunded' => 'البريد: معالجة الاسترداد',
        'refunded_desc' => 'يُرسل عند تسجيل كل استرداد.',
        'common' => 'البريد: التحية والزر',
        'common_desc' => 'تستخدمها كل رسائل العملاء أعلاه.',
    ],

    'labels' => [
        'sms.received_paid' => 'رسالة نصية: استلام الدفع',
        'sms.received_free' => 'رسالة نصية: استلام الطلب (مجاني)',
        'sms.completed' => 'رسالة نصية: اكتمال الطلب',
        'sms.rejected' => 'رسالة نصية: رفض الطلب',
        'sms.refunded' => 'رسالة نصية: معالجة الاسترداد',
        'mail.received.subject_paid' => 'العنوان (مدفوع)',
        'mail.received.subject_free' => 'العنوان (مجاني)',
        'mail.received.intro_paid' => 'السطر الافتتاحي (مدفوع)',
        'mail.received.intro_free' => 'السطر الافتتاحي (مجاني)',
        'mail.received.receipt_attached' => 'ملاحظة الإيصال',
        'mail.received.next' => 'ما يحدث بعد ذلك',
        'mail.completed.subject' => 'العنوان',
        'mail.completed.intro' => 'السطر الافتتاحي',
        'mail.completed.next' => 'السطر الختامي',
        'mail.rejected.subject' => 'العنوان',
        'mail.rejected.intro' => 'السطر الافتتاحي',
        'mail.rejected.reason' => 'عنوان «السبب»',
        'mail.rejected.refund' => 'سطر الاسترداد',
        'mail.refunded.subject' => 'العنوان',
        'mail.refunded.intro' => 'السطر الافتتاحي',
        'mail.refunded.timing' => 'سطر المدة',
        'mail.refunded.fee_kept' => 'سطر رسوم الخدمة',
        'mail.greeting' => 'التحية (مع الاسم)',
        'mail.greeting_guest' => 'التحية (بدون اسم)',
        'mail.track_button' => 'نص الزر',
    ],

    'placeholders' => 'يُملأ تلقائياً: :names',
    'no_placeholders' => 'نص عادي، لا شيء يُملأ تلقائياً.',

    'errors' => [
        'unknown' => 'هذه المتغيرات غير متاحة هنا: :names. يمكنك استخدام: :allowed.',
        'missing' => 'يجب أن يحتفظ هذا النص بـ :names (يحتاجها العميل).',
    ],

    'actions' => [
        'save' => 'حفظ النصوص',
        'reset' => 'إعادة الكل إلى الافتراضي',
        'reset_desc' => 'يعود كل نص غيّرته إلى صياغته الأصلية.',
    ],

    'notifications' => [
        'saved' => 'تم حفظ النصوص. تسري على الرسائل القادمة.',
        'reset' => 'عادت كل النصوص إلى الافتراضي.',
    ],
];

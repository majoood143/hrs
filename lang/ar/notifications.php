<?php

return [
    'sms' => [
        'received_paid' => ':site: تم استلام الدفع. الطلب :number (:service). تابعه: :url',
        'received_free' => ':site: تم استلام طلبك. الطلب :number (:service). تابعه: :url',
        'completed' => ':site: تم إكمال طلبك :number. التفاصيل: :url',
        'rejected' => ':site: تعذّرت الموافقة على طلبك :number. التفاصيل: :url',
        'refunded' => ':site: تمت معالجة استرداد :amount للطلب :number. التفاصيل: :url',
        'auction_approved' => ':site: يمكنك الآن المزايدة في :auction. :url',
        'auction_outbid' => ':site: تمت المزايدة عليك في الحصان :lot :horse (السعر الآن :price). زايد مجددًا: :url',
        'auction_ending' => ':site: الحصان :lot :horse يُغلق خلال :minutes دقيقة (السعر الآن :price). :url',
        'auction_won' => ':site: فزت بالحصان :lot :horse بسعر :price. ادفع :total قبل :due: :url',
        'auction_invoice_due' => ':site: فاتورة الحصان :lot :horse (:total) مستحقة في :due. ادفع: :url',
        'auction_application_approved' => ':site: قُبل حصانك :horse في :auction. تابعه: :url',
        'auction_application_rejected' => ':site: لا يمكننا عرض :horse في مزاد حاليًا: :reason :url',
        'auction_seller_sold' => ':site: بِيع حصانك :horse (رقم :lot) بسعر :price. سندفع لك :payout بعد دفع المشتري. :url',
        'auction_seller_unsold' => ':site: لم يبلغ حصانك :horse (رقم :lot) السعر الأدنى ولم يُبع. :url',
        'auction_seller_paid' => ':site: دفعنا لك :payout عن :horse (رقم :lot). :url',
        'game_award' => ':site: مبروك :name! حللت في المرتبة :rank في :campaign. جائزتك: :prize. سنتواصل معك لتسليمها.',
        'game_award_code' => ':site: مبروك :name! حللت في المرتبة :rank في :campaign. جائزتك: :prize. رمز الخصم :code يعمل لرقمك حتى :until عند حجز جلسة في إسطبل.',
    ],

    'mail' => [
        'greeting' => 'مرحباً :name،',
        'greeting_guest' => 'مرحباً،',
        'track_button' => 'عرض طلبي',

        'received' => [
            'subject_paid' => 'تم استلام الطلب :number: تأكيد الدفع (:site)',
            'subject_free' => 'تم استلام الطلب :number (:site)',
            'intro_paid' => 'شكراً لك. لقد استلمنا دفعتك وطلبك لخدمة **:service**.',
            'intro_free' => 'شكراً لك. لقد استلمنا طلبك لخدمة **:service**.',
            'receipt_attached' => 'إيصالك مرفق بهذه الرسالة.',
            'next' => 'سيراجع فريقنا طلبك وسنخبرك فور جاهزيته. احتفظ برقم طلبك: يمكنك استخدامه لمتابعة طلبك، أو تسجيل الدخول برقم هاتفك لعرض كل طلباتك.',
        ],

        'completed' => [
            'subject' => 'تم إكمال طلبك :number (:site)',
            'intro' => 'خبر سار: تم إكمال طلبك لخدمة **:service**.',
            'next' => 'يمكنك الاطلاع على تفاصيل طلبك في أي وقت.',
        ],

        'reviewer' => [
            'subject' => 'طلب مدفوع جديد :number: :service',
            'heading' => 'طلب مدفوع جديد :number',
            'intro' => 'دفع عميل مبلغ :total لخدمة **:service**. بياناته وإجاباته:',
            'button' => 'فتح الطلب',
        ],

        'rejected' => [
            'subject' => 'تعذّرت الموافقة على طلبك :number (:site)',
            'intro' => 'نأسف: لم نتمكن من الموافقة على طلبك لخدمة **:service**.',
            'reason' => 'السبب',
            'refund' => 'سيُعاد إليك مبلغ :amount (سعر الخدمة وضريبته). وسنؤكد لك عند إتمام الاسترداد.',
        ],

        'refunded' => [
            'subject' => 'استرداد الطلب :number (:site)',
            'intro' => 'تمت معالجة استرداد مبلغ **:amount** عن طلبك لخدمة **:service**.',
            'timing' => 'قد يستغرق ظهوره في حسابك بضعة أيام حسب مصرفك.',
            'fee_kept' => 'رسوم الخدمة (:amount، شاملة الضريبة) غير قابلة للاسترداد.',
        ],

        'stage' => [
            'subject' => 'الطلب :number بانتظار مراجعتك (:stage)',
            'heading' => 'الطلب :number بانتظارك',
            'intro' => 'طلب خدمة **:service** وصل إلى مرحلة **:stage** التي يعود القرار فيها إليك. ما كتبه العميل:',
            'button' => 'مراجعة الطلب',
        ],
    ],
];

<?php

// Arabic validation messages (the website's forms and the mobile API). A rule not listed here falls
// back to Laravel's English message. :attribute is the field's name from the form (already Arabic).
return [
    'accepted' => 'يجب قبول :attribute.',
    'after' => 'يجب أن يكون :attribute تاريخًا بعد :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخًا مساويًا لـ :date أو بعده.',
    'array' => 'يجب أن يكون :attribute قائمة.',
    'before' => 'يجب أن يكون :attribute تاريخًا قبل :date.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخًا مساويًا لـ :date أو قبله.',
    'boolean' => 'يجب أن تكون قيمة :attribute نعم أو لا.',
    'date' => 'يجب أن يكون :attribute تاريخًا صحيحًا.',
    'date_format' => 'يجب أن يطابق :attribute الصيغة :format.',
    'different' => 'يجب أن يختلف :attribute عن :other.',
    'digits' => 'يجب أن يتكوّن :attribute من :digits أرقام.',
    'email' => 'يجب أن يكون :attribute بريدًا إلكترونيًا صحيحًا.',
    'exists' => 'قيمة :attribute المختارة غير صحيحة.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => 'قيمة :attribute المختارة غير صحيحة.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عناصر.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا يزيد :attribute على :max.',
        'string' => 'يجب ألا يتجاوز :attribute :max حرفًا.',
    ],
    'mimes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عناصر على الأقل.',
        'file' => 'يجب أن يكون حجم :attribute :min كيلوبايت على الأقل.',
        'numeric' => 'يجب أن يكون :attribute :min على الأقل.',
        'string' => 'يجب أن يتكوّن :attribute من :min أحرف على الأقل.',
    ],
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'required' => 'حقل :attribute مطلوب.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other :value.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'uploaded' => 'تعذّر رفع :attribute.',
    'url' => 'يجب أن يكون :attribute رابطًا صحيحًا.',
];

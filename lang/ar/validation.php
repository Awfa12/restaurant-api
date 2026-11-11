<?php

/*
|--------------------------------------------------------------------------
| Fallback validation messages (Arabic)
|--------------------------------------------------------------------------
|
| Used only when a FormRequest doesn't give its own message. The real
| messages per field are ported word for word from the frontend's
| src/lib/dashboard/validation.ts into each FormRequest's messages().
|
| The messages don't use :attribute on purpose: field keys are camelCase
| English (isAvailable, optionGroups.0.options.1.priceDelta) and would read
| badly inside an Arabic sentence. The dashboard shows each message under
| its field, so the field name isn't needed.
|
| Only the rules this API uses are listed. Add a message here when a new
| rule starts being used.
|
*/

return [

    'accepted' => 'لازم توافق على هالشي',
    'array' => 'القيمة لازم تكون قائمة',
    'between' => [
        'array' => 'لازم يكون بين :min و :max عناصر',
        'file' => 'حجم الملف لازم يكون بين :min و :max كيلوبايت',
        'numeric' => 'الرقم لازم يكون بين :min و :max',
        'string' => 'لازم يكون بين :min و :max حرف',
    ],
    'boolean' => 'القيمة لازم تكون صح أو غلط',
    'distinct' => 'في قيمة مكررة',
    'enum' => 'القيمة المختارة مو موجودة',
    'exists' => 'العنصر المختار مو موجود',
    'file' => 'لازم ترفع ملف',
    'image' => 'الملف لازم يكون صورة',
    'in' => 'القيمة المختارة مو موجودة',
    'integer' => 'لازم يكون رقم صحيح',
    'list' => 'القيمة لازم تكون قائمة',
    'max' => [
        'array' => 'أقصى شي :max عناصر',
        'file' => 'الصورة كبيرة كتير، اختار صورة أصغر',
        'numeric' => 'الرقم لازم يكون :max أو أقل',
        'string' => 'لازم يكون :max حرف أو أقل',
    ],
    'mimes' => 'نوع الملف لازم يكون: :values',
    'mimetypes' => 'نوع الملف لازم يكون: :values',
    'min' => [
        'array' => 'لازم يكون في :min عناصر على الأقل',
        'file' => 'حجم الملف لازم يكون :min كيلوبايت على الأقل',
        'numeric' => 'الرقم لازم يكون :min أو أكتر',
        'string' => 'لازم يكون :min حرف على الأقل',
    ],
    'numeric' => 'لازم يكون رقم',
    'present' => 'هالحقل لازم يكون موجود',
    'prohibited' => 'هالحقل ممنوع هون',
    'regex' => 'الصيغة مو صحيحة',
    'required' => 'هالحقل مطلوب',
    'required_if' => 'هالحقل مطلوب',
    'required_with' => 'هالحقل مطلوب',
    'size' => [
        'array' => 'لازم يكون في :size عناصر بالضبط',
        'file' => 'حجم الملف لازم يكون :size كيلوبايت',
        'numeric' => 'الرقم لازم يكون :size',
        'string' => 'لازم يكون :size حرف بالضبط',
    ],
    'starts_with' => 'لازم يبلّش بـ :values',
    'string' => 'لازم يكون نص',
    'unique' => 'هالقيمة مستعملة من قبل',
    'uploaded' => 'ما قدرنا نرفع الملف، جرّب مرة تانية',
    'url' => 'الرابط مو صحيح',

    /*
    |--------------------------------------------------------------------------
    | Custom messages and attribute names
    |--------------------------------------------------------------------------
    |
    | Kept empty: per-field messages live in each FormRequest's messages().
    |
    */

    'custom' => [],

    'attributes' => [],

];

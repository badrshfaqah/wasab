<?php
/** صلاحيات إضافة المصروفات. */
return [
    ['key' => 'expenses.submit', 'label' => 'تقديم طلب مصروف', 'default_level' => 'employee'],
    ['key' => 'expenses.manage', 'label' => 'اعتماد/رفض المصروفات ومشاهدة الكل', 'default_level' => 'manager'],
];

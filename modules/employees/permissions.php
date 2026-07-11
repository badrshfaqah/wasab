<?php
/**
 * صلاحيات إضافة الملف الوظيفي. تُزرع تلقائياً في جدول permissions عند التثبيت/التحديث.
 * default_level تصنيف إرشادي فقط (موظف/مدير) - انظر ملاحظة modules/tasks/permissions.php.
 * كلها بمستوى "مدير" افتراضياً لأن الوصول لملفات الموظفين الوظيفية عمل إداري بطبيعته؛
 * الموظف نفسه يشاهد ملفه الخاص (المرتبط بحسابه) دون أي صلاحية عبر استثناء بالكنترولر.
 */
return [
    ['key' => 'employees.view', 'label' => 'مشاهدة الملفات الوظيفية', 'default_level' => 'manager'],
    ['key' => 'employees.view_sensitive', 'label' => 'عرض الراتب والبيانات البنكية والتأمينات', 'default_level' => 'manager'],
    ['key' => 'employees.create', 'label' => 'إضافة ملف وظيفي', 'default_level' => 'manager'],
    ['key' => 'employees.edit', 'label' => 'تعديل الملف الوظيفي', 'default_level' => 'manager'],
    ['key' => 'employees.delete', 'label' => 'حذف الملف الوظيفي', 'default_level' => 'manager'],
    ['key' => 'employees.manage', 'label' => 'إدارة كاملة لكل الملفات الوظيفية', 'default_level' => 'manager'],
];

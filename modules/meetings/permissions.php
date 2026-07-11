<?php
/**
 * صلاحيات إضافة الاجتماعات. تُزرع تلقائياً في جدول permissions عند التثبيت/التحديث.
 * default_level تصنيف إرشادي فقط (موظف/مدير) - انظر ملاحظة modules/tasks/permissions.php.
 */
return [
    ['key' => 'meetings.view', 'label' => 'مشاهدة الاجتماعات', 'default_level' => 'employee'],
    ['key' => 'meetings.create', 'label' => 'إنشاء اجتماع', 'default_level' => 'employee'],
    ['key' => 'meetings.edit', 'label' => 'تعديل الاجتماعات', 'default_level' => 'employee'],
    ['key' => 'meetings.delete', 'label' => 'حذف الاجتماعات', 'default_level' => 'manager'],
    ['key' => 'meetings.manage', 'label' => 'إدارة كاملة لجميع اجتماعات الشركة', 'default_level' => 'manager'],
];

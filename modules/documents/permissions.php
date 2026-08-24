<?php
/**
 * صلاحيات إضافة المستندات. تُزرع تلقائياً في جدول permissions عند التثبيت/التحديث.
 * default_level تصنيف إرشادي فقط (موظف/مدير) - انظر ملاحظة modules/tasks/permissions.php.
 */
return [
    ['key' => 'documents.view', 'label' => 'مشاهدة المستندات', 'default_level' => 'employee'],
    ['key' => 'documents.create', 'label' => 'إنشاء مستند', 'default_level' => 'employee'],
    ['key' => 'documents.edit', 'label' => 'تعديل المستندات', 'default_level' => 'employee'],
    ['key' => 'documents.delete', 'label' => 'حذف المستندات', 'default_level' => 'manager'],
    ['key' => 'documents.sign', 'label' => 'الإصدار الرسمي (توقيع المستندات)', 'default_level' => 'manager'],
    ['key' => 'documents.manage', 'label' => 'إدارة كاملة: كل المستندات، القوالب، وإعدادات الإضافة', 'default_level' => 'manager'],
];

<?php
/**
 * صلاحيات إضافة أرشيف الملفات. تُزرع تلقائياً في جدول permissions عند التثبيت/التحديث.
 * صلاحية الوصول الفعلي لملف/تصنيف معيّن تُضبط بشكل منفصل عبر إعداد "من يراه"
 * الخاص بكل ملف/تصنيف (archive_files.visibility_type)، وليس عبر هذه المفاتيح.
 * default_level تصنيف إرشادي فقط (موظف/مدير) - انظر ملاحظة modules/tasks/permissions.php.
 */
return [
    ['key' => 'archive.view', 'label' => 'مشاهدة الملفات', 'default_level' => 'employee'],
    ['key' => 'archive.create', 'label' => 'رفع الملفات', 'default_level' => 'employee'],
    ['key' => 'archive.edit', 'label' => 'تعديل الملفات', 'default_level' => 'employee'],
    ['key' => 'archive.delete', 'label' => 'حذف الملفات', 'default_level' => 'manager'],
    ['key' => 'archive.download', 'label' => 'تحميل الملفات', 'default_level' => 'employee'],
    ['key' => 'archive.share', 'label' => 'مشاركة الملفات برابط مؤقت', 'default_level' => 'manager'],
    ['key' => 'archive.categories.create', 'label' => 'إنشاء التصنيفات', 'default_level' => 'manager'],
    ['key' => 'archive.categories.edit', 'label' => 'تعديل التصنيفات', 'default_level' => 'manager'],
    ['key' => 'archive.categories.delete', 'label' => 'حذف التصنيفات', 'default_level' => 'manager'],
    ['key' => 'archive.manage', 'label' => 'إدارة كاملة لجميع الملفات والتصنيفات والإعدادات', 'default_level' => 'manager'],
];

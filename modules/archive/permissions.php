<?php
/**
 * صلاحيات إضافة أرشيف الملفات. تُزرع تلقائياً في جدول permissions عند التثبيت/التحديث.
 * صلاحية الوصول الفعلي لملف/تصنيف معيّن تُضبط بشكل منفصل عبر إعداد "من يراه"
 * الخاص بكل ملف/تصنيف (archive_files.visibility_type)، وليس عبر هذه المفاتيح.
 */
return [
    ['key' => 'archive.view', 'label' => 'مشاهدة الملفات'],
    ['key' => 'archive.create', 'label' => 'رفع الملفات'],
    ['key' => 'archive.edit', 'label' => 'تعديل الملفات'],
    ['key' => 'archive.delete', 'label' => 'حذف الملفات'],
    ['key' => 'archive.download', 'label' => 'تحميل الملفات'],
    ['key' => 'archive.share', 'label' => 'مشاركة الملفات برابط مؤقت'],
    ['key' => 'archive.categories.create', 'label' => 'إنشاء التصنيفات'],
    ['key' => 'archive.categories.edit', 'label' => 'تعديل التصنيفات'],
    ['key' => 'archive.categories.delete', 'label' => 'حذف التصنيفات'],
    ['key' => 'archive.manage', 'label' => 'إدارة كاملة لجميع الملفات والتصنيفات والإعدادات'],
];

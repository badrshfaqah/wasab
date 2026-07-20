<?php
/**
 * صلاحيات إضافة مركز المراسلات. تُزرع تلقائياً في جدول permissions عند التثبيت/التحديث.
 * default_level تصنيف إرشادي فقط (موظف/مدير) يظهر عند إنشاء دور جديد.
 */
return [
    ['key' => 'inbox.view', 'label' => 'مشاهدة رسائل مركز المراسلات', 'default_level' => 'employee'],
    ['key' => 'inbox.mark_read', 'label' => 'تأشير الرسائل كمقروءة/غير مقروءة', 'default_level' => 'employee'],
    ['key' => 'inbox.delete', 'label' => 'حذف الرسائل', 'default_level' => 'manager'],
    ['key' => 'inbox.manage_sites', 'label' => 'إدارة المواقع المصدر ومفاتيح الربط', 'default_level' => 'manager'],
];

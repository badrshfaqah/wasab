<?php
/**
 * صلاحيات إضافة المستندات. تُزرع تلقائياً في جدول permissions عند التثبيت/التحديث.
 */
return [
    ['key' => 'documents.view', 'label' => 'مشاهدة المستندات'],
    ['key' => 'documents.create', 'label' => 'إنشاء مستند'],
    ['key' => 'documents.edit', 'label' => 'تعديل المستندات (قبل الاعتماد)'],
    ['key' => 'documents.delete', 'label' => 'حذف المستندات'],
    ['key' => 'documents.approve', 'label' => 'اعتماد المستندات'],
    ['key' => 'documents.sign', 'label' => 'توقيع المستندات'],
    ['key' => 'documents.manage', 'label' => 'إدارة كاملة: كل المستندات، القوالب، وإعدادات الإضافة'],
];

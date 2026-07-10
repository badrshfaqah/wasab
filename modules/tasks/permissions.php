<?php
/**
 * صلاحيات إضافة المهام. تُزرع تلقائياً في جدول permissions عند التثبيت/التحديث.
 */
return [
    ['key' => 'tasks.view', 'label' => 'مشاهدة المهام'],
    ['key' => 'tasks.create', 'label' => 'إضافة مهمة'],
    ['key' => 'tasks.edit', 'label' => 'تعديل المهام'],
    ['key' => 'tasks.delete', 'label' => 'حذف المهام'],
    ['key' => 'tasks.approve', 'label' => 'اعتماد المهام'],
    ['key' => 'tasks.manage', 'label' => 'إدارة كاملة لجميع مهام الشركة'],
];

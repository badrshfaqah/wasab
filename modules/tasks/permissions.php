<?php
/**
 * صلاحيات إضافة المهام. تُزرع تلقائياً في جدول permissions عند التثبيت/التحديث.
 * default_level تصنيف إرشادي فقط (موظف/مدير) يظهر عند إنشاء دور جديد لمساعدة من
 * يبني أدواراً مستقبلية على معرفة أي الصلاحيات عادة تُمنح لموظف عادي أو لمدير -
 * لا يُطبَّق تلقائياً ولا يمنع تعيين أي صلاحية لأي دور.
 */
return [
    ['key' => 'tasks.view', 'label' => 'مشاهدة المهام', 'default_level' => 'employee'],
    ['key' => 'tasks.create', 'label' => 'إضافة مهمة', 'default_level' => 'employee'],
    ['key' => 'tasks.edit', 'label' => 'تعديل المهام', 'default_level' => 'employee'],
    ['key' => 'tasks.delete', 'label' => 'حذف المهام', 'default_level' => 'manager'],
    ['key' => 'tasks.approve', 'label' => 'اعتماد المهام', 'default_level' => 'manager'],
    ['key' => 'tasks.manage', 'label' => 'إدارة كاملة لجميع مهام الشركة', 'default_level' => 'manager'],
];

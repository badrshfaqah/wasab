<?php
/** صلاحيات إضافة العملاء. */
return [
    ['key' => 'clients.view', 'label' => 'مشاهدة العملاء', 'default_level' => 'employee'],
    ['key' => 'clients.create', 'label' => 'إضافة عميل', 'default_level' => 'employee'],
    ['key' => 'clients.edit', 'label' => 'تعديل بيانات العملاء', 'default_level' => 'manager'],
    ['key' => 'clients.delete', 'label' => 'حذف/أرشفة العملاء', 'default_level' => 'manager'],
];

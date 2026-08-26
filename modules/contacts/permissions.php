<?php
/** صلاحيات دليل جهات الاتصال. */
return [
    ['key' => 'contacts.view', 'label' => 'مشاهدة دليل جهات الاتصال', 'default_level' => 'employee'],
    ['key' => 'contacts.create', 'label' => 'إضافة جهة أو فرد', 'default_level' => 'employee'],
    ['key' => 'contacts.edit', 'label' => 'تعديل بيانات الجهات والأفراد', 'default_level' => 'employee'],
    ['key' => 'contacts.delete', 'label' => 'أرشفة/حذف من الدليل', 'default_level' => 'manager'],
];

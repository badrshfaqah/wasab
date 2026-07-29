<?php
/**
 * صلاحيات إضافة العهد والأصول. إدارة الأصول عمل إداري بطبيعته فأغلبها بمستوى "مدير".
 * assets.view_own استثناء: الموظف المربوط بحساب يرى العهد المسندة إليه هو فقط.
 */
return [
    ['key' => 'assets.view', 'label' => 'مشاهدة الأصول والعهد', 'default_level' => 'manager'],
    ['key' => 'assets.view_own', 'label' => 'مشاهدة العهد المسندة لي فقط', 'default_level' => 'employee'],
    ['key' => 'assets.create', 'label' => 'إضافة أصل', 'default_level' => 'manager'],
    ['key' => 'assets.edit', 'label' => 'تعديل الأصول', 'default_level' => 'manager'],
    ['key' => 'assets.delete', 'label' => 'حذف الأصول', 'default_level' => 'manager'],
    ['key' => 'assets.assign', 'label' => 'إسناد وإرجاع العهد (محاضر التسليم)', 'default_level' => 'manager'],
    ['key' => 'assets.manage', 'label' => 'إدارة كاملة: أصول، تصنيفات، محاضر', 'default_level' => 'manager'],
];

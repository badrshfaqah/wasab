<?php
/**
 * صلاحيات إضافة CRM على مستوى النظام. الصلاحيات التفصيلية داخل كل مساحة
 * (إضافة جهة، تسجيل نشاط، إدارة المراحل...) تُحفظ لكل عضو في جدول
 * crm_workspace_members - انظر Workspace::abilities().
 */
return [
    ['key' => 'crm.view', 'label' => 'الدخول إلى CRM', 'default_level' => 'employee'],
    ['key' => 'crm.workspace.create', 'label' => 'إنشاء مساحة CRM', 'default_level' => 'manager'],
    ['key' => 'crm.manage', 'label' => 'إدارة كاملة: كل المساحات ودليل الجهات وإعدادات الإضافة', 'default_level' => 'manager'],
];

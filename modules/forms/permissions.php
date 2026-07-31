<?php
/** صلاحيات إضافة النماذج. توليد الخطابات وإدارة القوالب عمل إداري بطبيعته. */
return [
    ['key' => 'forms.view', 'label' => 'مشاهدة النماذج والخطابات المولّدة', 'default_level' => 'manager'],
    ['key' => 'forms.generate', 'label' => 'توليد خطاب من قالب', 'default_level' => 'manager'],
    ['key' => 'forms.delete', 'label' => 'حذف الخطابات المولّدة', 'default_level' => 'manager'],
    ['key' => 'forms.manage', 'label' => 'إدارة كاملة: القوالب والإعدادات والترويسة', 'default_level' => 'manager'],
];

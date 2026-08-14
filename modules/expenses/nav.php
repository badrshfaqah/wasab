<?php
/** رابط القائمة الجانبية لإضافة المصروفات. */
return function (array $user): array {
    if (\App\Core\Permission::check('expenses.submit') || \App\Core\Permission::check('expenses.manage')) {
        return [['label' => 'المصروفات', 'icon' => '💰', 'url' => route('/expenses')]];
    }
    return [];
};

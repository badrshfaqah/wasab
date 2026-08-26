<?php
/** رابط القائمة الجانبية لدليل جهات الاتصال. */
return function (array $user): array {
    if (\App\Core\Permission::check('contacts.view')) {
        return [['label' => 'جهات الاتصال', 'icon' => '📇', 'url' => route('/contacts')]];
    }
    return [];
};

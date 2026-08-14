<?php
/** رابط القائمة الجانبية لإضافة العملاء. */
return function (array $user): array {
    if (\App\Core\Permission::check('clients.view')) {
        return [['label' => 'العملاء', 'icon' => '👔', 'url' => route('/clients')]];
    }
    return [];
};

<?php

use App\Core\Auth;
use App\Core\Permission;

return function (array $user): array {
    $items = [];

    if (Permission::check('phone.view')) {
        $items[] = ['label' => 'الهاتف', 'icon' => '📞', 'url' => route('/phone/settings')];
        $items[] = ['label' => 'دليل جهات الاتصال', 'icon' => '📇', 'url' => route('/phone/contacts')];
    }

    if (Auth::isSystemAdmin()) {
        $items[] = ['label' => 'إعدادات الهاتف (الشركات)', 'icon' => '📶', 'url' => route('/phone/admin')];
    }

    return $items;
};

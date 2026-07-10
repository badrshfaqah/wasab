<?php

use App\Core\Permission;
use Modules\Phone\Models\PhoneUser;

return function (array $user): array {
    if (!Permission::check('phone.view')) {
        return [];
    }

    $phoneUser = PhoneUser::forUser((int) $user['id']);
    $configured = $phoneUser && $phoneUser['enabled'];

    return [[
        'type' => 'stat',
        'label' => $configured ? 'التحويلة: ' . $phoneUser['extension'] : 'الهاتف غير مُهيأ',
        'value' => $configured ? '📞' : '⚠️',
        'icon' => '',
        'color' => $configured ? 'success' : 'warning',
        'url' => route('/phone/settings'),
    ]];
};

<?php

use App\Core\Permission;
use App\Core\View;
use Modules\Phone\Models\PhoneCompanySetting;
use Modules\Phone\Models\PhoneUser;

/**
 * ودجت الهاتف العائم - يُحقن في كل صفحة (عبر layouts/app.php) لمن يملك صلاحية phone.view فقط.
 */
return function (array $user): array {
    if (!Permission::check('phone.view') || empty($user['company_id'])) {
        return [];
    }

    $companySetting = PhoneCompanySetting::forCompany((int) $user['company_id']);
    $apiKey = $companySetting['api_key'] ?? '';

    if ($apiKey === '') {
        return [View::renderPartial('phone::widget', ['state' => 'missing_company_key'])];
    }

    $phoneUser = PhoneUser::forUser((int) $user['id']);
    if (!$phoneUser || !$phoneUser['enabled'] || $phoneUser['extension'] === '' || $phoneUser['secret'] === '') {
        return [View::renderPartial('phone::widget', ['state' => 'missing_user_config'])];
    }

    return [View::renderPartial('phone::widget', [
        'state' => 'ready',
        'apiKey' => $apiKey,
        'extension' => $phoneUser['extension'],
        'secret' => $phoneUser['secret'],
    ])];
};

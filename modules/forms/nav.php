<?php
return function (array $user): array {
    if (empty($user['company_id']) || !\App\Core\Permission::check('forms.view')) {
        return [];
    }
    return [[
        'label' => 'النماذج',
        'icon' => '📝',
        'url' => route('/forms'),
    ]];
};

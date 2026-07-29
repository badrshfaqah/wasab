<?php
/** رابط القائمة الجانبية - يظهر لمن يملك مشاهدة الأصول أو مشاهدة عهده الخاصة. */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    if (!\App\Core\Permission::check('assets.view') && !\App\Core\Permission::check('assets.view_own')) {
        return [];
    }

    return [
        [
            'label' => 'العهد والأصول',
            'icon' => '📦',
            'url' => route('/assets'),
        ],
    ];
};

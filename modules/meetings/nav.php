<?php
/**
 * رابط القائمة الجانبية لإضافة الاجتماعات. يُستدعى فقط عندما تكون الإضافة مفعّلة.
 */
return function (array $user): array {
    if (!\App\Core\Permission::check('meetings.view')) {
        return [];
    }
    return [
        ['label' => 'الاجتماعات', 'icon' => '🗓️', 'url' => route('/meetings')],
    ];
};

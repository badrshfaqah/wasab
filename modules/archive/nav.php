<?php
/**
 * رابط القائمة الجانبية لإضافة أرشيف الملفات. يُستدعى فقط عندما تكون الإضافة مفعّلة.
 */
return function (array $user): array {
    if (!\App\Core\Permission::check('archive.view') && !\App\Core\Permission::check('archive.manage')) {
        return [];
    }
    return [
        ['label' => 'أرشيف الملفات', 'icon' => '🗂️', 'url' => route('/archive')],
    ];
};

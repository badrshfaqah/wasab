<?php
/**
 * رابط القائمة الجانبية لإضافة المستندات. يُستدعى فقط عندما تكون الإضافة مفعّلة.
 */
return function (array $user): array {
    if (!\App\Core\Permission::check('documents.view')) {
        return [];
    }
    return [
        ['label' => 'المستندات', 'icon' => '📄', 'url' => route('/documents')],
    ];
};

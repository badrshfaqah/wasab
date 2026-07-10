<?php
/**
 * رابط القائمة الجانبية لإضافة المهام. يُستدعى فقط عندما تكون الإضافة مفعّلة.
 */
return function (array $user): array {
    if (!\App\Core\Permission::check('tasks.view')) {
        return [];
    }
    return [
        ['label' => 'المهام', 'icon' => '📋', 'url' => route('/tasks')],
    ];
};

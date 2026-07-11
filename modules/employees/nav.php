<?php
/**
 * رابط القائمة الجانبية لإضافة الملف الوظيفي. يُستدعى فقط عندما تكون الإضافة مفعّلة.
 * لمن يملك صلاحية المشاهدة العامة: رابط لقائمة كل الملفات. لموظف عادي مرتبط حسابه
 * بملف وظيفي فقط (بلا صلاحية): رابط مباشر لملفه الخاص (صفحة القائمة نفسها محصورة
 * بالصلاحية العامة، فلا فائدة من توجيهه إليها).
 */
return function (array $user): array {
    $canViewList = \App\Core\Permission::check('employees.view') || \App\Core\Permission::check('employees.manage');
    if ($canViewList) {
        return [['label' => 'الملف الوظيفي', 'icon' => '🪪', 'url' => route('/employees')]];
    }

    if (empty($user['company_id'])) {
        return [];
    }

    $own = \App\Core\Database::first(
        'SELECT id FROM employees_profiles WHERE company_id = :c AND linked_user_id = :u',
        ['c' => $user['company_id'], 'u' => $user['id']]
    );

    if (!$own) {
        return [];
    }

    return [['label' => 'ملفي الوظيفي', 'icon' => '🪪', 'url' => route('/employees/' . $own['id'])]];
};

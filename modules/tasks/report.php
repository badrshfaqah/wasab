<?php

use App\Core\Database;

/**
 * أرقام "المهام" لصفحة التقارير - على مستوى الشركة كاملة (لمدير الشركة)، بلا فلترة حسب
 * صلاحيات مستخدم معيّن.
 */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];

    $open = Database::count('tasks_tasks', 'company_id = :c AND status NOT IN ("done", "cancelled")', ['c' => $companyId]);
    $overdue = Database::count('tasks_tasks', 'company_id = :c AND status NOT IN ("done", "cancelled") AND due_date IS NOT NULL AND due_date < CURDATE()', ['c' => $companyId]);
    $done = Database::count('tasks_tasks', 'company_id = :c AND status = "done"', ['c' => $companyId]);

    return [
        ['label' => 'مهام مفتوحة', 'value' => $open, 'icon' => '📋', 'color' => 'primary', 'url' => route('/tasks?scope=all')],
        ['label' => 'مهام متأخرة', 'value' => $overdue, 'icon' => '⏰', 'color' => $overdue > 0 ? 'danger' : 'success', 'url' => route('/tasks?scope=overdue')],
        ['label' => 'مهام منجزة', 'value' => $done, 'icon' => '✅', 'color' => 'success'],
    ];
};

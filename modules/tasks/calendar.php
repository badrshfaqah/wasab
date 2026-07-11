<?php

use App\Core\Database;
use App\Core\Permission;

/**
 * مزوّد أحداث التقويم الموحّد من إضافة المهام: تاريخ استحقاق كل مهمة يراها المستخدم
 * (مهامه أو التي أنشأها، أو الكل لمن يدير الإضافة).
 */
return function (array $user, string $fromDate, string $toDate): array {
    if (!Permission::check('tasks.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $canManage = $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin' || Permission::check('tasks.manage');

    $where = 'company_id = :c AND due_date IS NOT NULL AND due_date BETWEEN :from AND :to';
    $params = ['c' => $companyId, 'from' => $fromDate, 'to' => $toDate];
    if (!$canManage) {
        $where .= ' AND (assignee_id = :u1 OR creator_id = :u2)';
        $params['u1'] = $userId;
        $params['u2'] = $userId;
    }

    $rows = Database::select("SELECT id, title, due_date FROM tasks_tasks WHERE {$where}", $params);

    return array_map(fn ($t) => [
        'date' => $t['due_date'],
        'title' => '📋 ' . $t['title'],
        'url' => route('/tasks/' . $t['id']),
    ], $rows);
};

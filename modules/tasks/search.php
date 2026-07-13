<?php

use App\Core\Database;
use App\Core\Permission;

/**
 * مزوّد البحث الموحّد من إضافة المهام: يبحث بعنوان المهمة، ويقتصر على مهام المستخدم
 * (المسندة له أو التي أنشأها) إلا لمن يدير الإضافة.
 */
return function (array $user, string $query): array {
    if (!Permission::check('tasks.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $canManage = $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin' || Permission::check('tasks.manage');

    $where = 'company_id = :c AND title LIKE :q';
    $params = ['c' => $companyId, 'q' => '%' . $query . '%'];
    if (!$canManage) {
        $where .= ' AND (assignee_id = :u1 OR creator_id = :u2)';
        $params['u1'] = $userId;
        $params['u2'] = $userId;
    }

    $rows = Database::select("SELECT id, title, status FROM tasks_tasks WHERE {$where} ORDER BY id DESC LIMIT 8", $params);

    return array_map(fn ($t) => [
        'title' => $t['title'],
        'subtitle' => status_label($t['status']),
        'icon' => '📋',
        'url' => route('/tasks/' . $t['id']),
    ], $rows);
};

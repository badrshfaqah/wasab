<?php

use App\Core\Permission;
use Modules\Tasks\Models\Task;

/**
 * عناصر الصفحة الرئيسية لإضافة المهام. يُستدعى فقط عندما تكون الإضافة مفعّلة،
 * ويُفلتر تلقائياً حسب صلاحية المستخدم الحالي.
 */
return function (array $user): array {
    if (!Permission::check('tasks.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];

    $widgets = [];

    $overdue = Task::countOverdue($companyId, $userId);
    $widgets[] = [
        'type' => 'stat',
        'label' => 'مهامي المتأخرة',
        'value' => $overdue,
        'icon' => '⏰',
        'color' => $overdue > 0 ? 'danger' : 'success',
        'url' => route('/tasks?scope=overdue'),
    ];

    if (Permission::check('tasks.approve')) {
        $pending = Task::countPendingApproval($companyId, $userId);
        $widgets[] = [
            'type' => 'stat',
            'label' => 'مهام تحتاج اعتمادي',
            'value' => $pending,
            'icon' => '✅',
            'color' => $pending > 0 ? 'warning' : 'success',
            'url' => route('/tasks?scope=approval'),
        ];
    }

    if (Permission::check('tasks.create')) {
        $widgets[] = [
            'type' => 'shortcut',
            'label' => 'مهمة جديدة',
            'icon' => '➕',
            'url' => route('/tasks/create'),
        ];
    }

    $recent = Task::recentForUser($companyId, $userId, 5);
    $widgets[] = [
        'type' => 'list',
        'title' => 'مهامي القادمة',
        'icon' => '📋',
        'empty_text' => 'لا توجد مهام قادمة عليك حالياً',
        'items' => array_map(fn ($t) => [
            'label' => $t['title'],
            'url' => route('/tasks/' . $t['id']),
            'meta' => $t['due_date'] ? format_date($t['due_date']) : '',
        ], $recent),
    ];

    return $widgets;
};

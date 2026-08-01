<?php

use App\Core\Database;
use App\Core\Notification;
use Modules\Tasks\Models\Task;

/**
 * تصعيد المهام المتأخرة تلقائياً - مرة واحدة لكل مهمة عند تجاوز موعد استحقاقها (يُعاد
 * تفعيله إن غُيّر الموعد). يصل تذكير للمسؤول، وتصعيد للمكلِّف (المنشئ) ولمدراء الشركة.
 * يُستدعى من ModuleManager::runCron() عبر cron.php بجذر المشروع.
 */
return function (): void {
    $tasks = Task::overdueNotEscalated();
    if (!$tasks) {
        return;
    }

    // مدراء كل شركة (يُجلبون مرة واحدة لكل شركة عند الحاجة)
    $adminsByCompany = [];
    $adminsFor = function (int $companyId) use (&$adminsByCompany): array {
        if (!isset($adminsByCompany[$companyId])) {
            $rows = Database::select(
                "SELECT id FROM users
                  WHERE company_id = :c AND status = 'active' AND membership_type = 'company_admin'",
                ['c' => $companyId]
            );
            $adminsByCompany[$companyId] = array_map(fn ($r) => (int) $r['id'], $rows);
        }
        return $adminsByCompany[$companyId];
    };

    foreach ($tasks as $task) {
        $taskId = (int) $task['id'];
        $companyId = (int) $task['company_id'];
        $url = route('/tasks/' . $taskId);
        $due = $task['due_date'];

        // تذكير للمسؤول عن المهمة
        if (!empty($task['assignee_id'])) {
            Notification::send(
                (int) $task['assignee_id'],
                '⏰ مهمة متأخرة',
                'تجاوزت المهمة "' . $task['title'] . '" موعدها (' . $due . ').',
                $url
            );
        }

        // تصعيد للمكلِّف ولمدراء الشركة (عدا المسؤول نفسه لتفادي التكرار)
        $escalateTo = array_merge([(int) $task['creator_id']], $adminsFor($companyId));
        foreach (array_unique($escalateTo) as $uid) {
            if ($uid > 0 && $uid !== (int) $task['assignee_id']) {
                Notification::send(
                    $uid,
                    '🚨 تصعيد: مهمة متأخرة',
                    'المهمة "' . $task['title'] . '" تجاوزت موعدها (' . $due . ') ولم تُنجَز.',
                    $url
                );
            }
        }

        Database::update('tasks_tasks', ['escalated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $taskId]);
    }
};

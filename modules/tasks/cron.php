<?php

use App\Core\Database;
use App\Core\Notification;
use Modules\Tasks\Models\RecurringTask;
use Modules\Tasks\Models\Task;

/**
 * مهام الجدولة الدورية للمهام:
 *  1) توليد المهام المتكررة المستحقة (يومي/أسبوعي/شهري).
 *  2) تصعيد المهام المتأخرة (مرة واحدة لكل مهمة، يُعاد تفعيله إن غُيّر الموعد).
 * يُستدعى من ModuleManager::runCron() عبر cron.php بجذر المشروع.
 */
return function (): void {
    // ---- (1) توليد المهام المتكررة المستحقة ----
    $today = date('Y-m-d');
    foreach (RecurringTask::due() as $r) {
        $creatorId = (int) ($r['created_by'] ?: $r['assignee_id']);
        $dueDate = (int) $r['due_offset_days'] > 0
            ? date('Y-m-d', strtotime("+{$r['due_offset_days']} days"))
            : $today;

        $taskId = Task::create([
            'company_id' => (int) $r['company_id'],
            'title' => $r['title'],
            'description' => $r['description'],
            'assignee_id' => (int) $r['assignee_id'],
            'creator_id' => $creatorId,
            'start_date' => $today,
            'due_date' => $dueDate,
            'priority' => $r['priority'],
            'status' => 'todo',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // نقدّم next_run حتى يتجاوز اليوم (نولّد مهمة واحدة فقط حتى لو فات cron عدة أيام)
        $next = $r['next_run'];
        do {
            $next = RecurringTask::advance($next, $r['frequency']);
        } while ($next <= $today);
        RecurringTask::update((int) $r['id'], ['next_run' => $next]);

        Notification::send((int) $r['assignee_id'], '🔁 مهمة متكررة جديدة', $r['title'], route('/tasks/' . $taskId));
    }

    // ---- (2) تصعيد المهام المتأخرة ----
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

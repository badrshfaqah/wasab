<?php

namespace Modules\Tasks\Models;

use App\Core\Database;

class TaskLog
{
    public static function add(int $taskId, ?int $userId, string $action, string $description): void
    {
        Database::insert('tasks_logs', [
            'task_id' => $taskId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forTask(int $taskId): array
    {
        return Database::select(
            'SELECT tl.*, u.name AS user_name
               FROM tasks_logs tl LEFT JOIN users u ON u.id = tl.user_id
              WHERE tl.task_id = :t ORDER BY tl.id DESC',
            ['t' => $taskId]
        );
    }
}

<?php

namespace Modules\Tasks\Models;

use App\Core\Database;

class TaskComment
{
    public static function add(int $taskId, int $userId, string $body): int
    {
        return Database::insert('tasks_comments', [
            'task_id' => $taskId,
            'user_id' => $userId,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forTask(int $taskId): array
    {
        return Database::select(
            'SELECT tc.*, u.name AS user_name
               FROM tasks_comments tc LEFT JOIN users u ON u.id = tc.user_id
              WHERE tc.task_id = :t ORDER BY tc.id ASC',
            ['t' => $taskId]
        );
    }
}

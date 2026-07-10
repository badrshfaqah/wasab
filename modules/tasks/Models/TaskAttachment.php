<?php

namespace Modules\Tasks\Models;

use App\Core\Database;

class TaskAttachment
{
    public static function add(int $taskId, int $userId, string $originalName, string $storedName, int $size): int
    {
        return Database::insert('tasks_attachments', [
            'task_id' => $taskId,
            'user_id' => $userId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'size' => $size,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forTask(int $taskId): array
    {
        return Database::select(
            'SELECT ta.*, u.name AS user_name
               FROM tasks_attachments ta LEFT JOIN users u ON u.id = ta.user_id
              WHERE ta.task_id = :t ORDER BY ta.id DESC',
            ['t' => $taskId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM tasks_attachments WHERE id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('tasks_attachments', 'id = :id', ['id' => $id]);
    }
}

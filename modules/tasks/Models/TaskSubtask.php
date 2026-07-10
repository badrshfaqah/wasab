<?php

namespace Modules\Tasks\Models;

use App\Core\Database;

class TaskSubtask
{
    public static function forTask(int $taskId): array
    {
        return Database::select(
            'SELECT * FROM tasks_subtasks WHERE task_id = :t ORDER BY position ASC, id ASC',
            ['t' => $taskId]
        );
    }

    public static function progress(int $taskId): array
    {
        $row = Database::first(
            'SELECT COUNT(*) AS total, SUM(is_done) AS done FROM tasks_subtasks WHERE task_id = :t',
            ['t' => $taskId]
        );
        return ['total' => (int) ($row['total'] ?? 0), 'done' => (int) ($row['done'] ?? 0)];
    }

    public static function add(int $taskId, string $title): int
    {
        $position = (int) (Database::first(
            'SELECT COALESCE(MAX(position), -1) + 1 AS next_position FROM tasks_subtasks WHERE task_id = :t',
            ['t' => $taskId]
        )['next_position'] ?? 0);

        return Database::insert('tasks_subtasks', [
            'task_id' => $taskId,
            'title' => $title,
            'is_done' => 0,
            'position' => $position,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM tasks_subtasks WHERE id = :id', ['id' => $id]);
    }

    public static function toggle(int $id, bool $done): void
    {
        Database::update('tasks_subtasks', [
            'is_done' => $done ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('tasks_subtasks', 'id = :id', ['id' => $id]);
    }
}

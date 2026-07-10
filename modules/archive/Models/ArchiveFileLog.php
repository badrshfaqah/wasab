<?php

namespace Modules\Archive\Models;

use App\Core\Database;

class ArchiveFileLog
{
    public static function add(int $fileId, ?int $userId, string $action, string $description): void
    {
        Database::insert('archive_file_logs', [
            'file_id' => $fileId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forFile(int $fileId): array
    {
        return Database::select(
            'SELECT l.*, u.name AS user_name
               FROM archive_file_logs l
               LEFT JOIN users u ON u.id = l.user_id
              WHERE l.file_id = :id
              ORDER BY l.id DESC',
            ['id' => $fileId]
        );
    }
}

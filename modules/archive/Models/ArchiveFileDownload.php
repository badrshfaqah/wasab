<?php

namespace Modules\Archive\Models;

use App\Core\Database;

class ArchiveFileDownload
{
    public static function add(int $fileId, ?int $userId): void
    {
        Database::insert('archive_file_downloads', [
            'file_id' => $fileId,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forFile(int $fileId, int $limit = 30): array
    {
        return Database::select(
            "SELECT d.*, u.name AS user_name
               FROM archive_file_downloads d
               LEFT JOIN users u ON u.id = d.user_id
              WHERE d.file_id = :id
              ORDER BY d.id DESC
              LIMIT {$limit}",
            ['id' => $fileId]
        );
    }
}

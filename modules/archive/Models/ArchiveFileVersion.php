<?php

namespace Modules\Archive\Models;

use App\Core\Database;

class ArchiveFileVersion
{
    public static function add(int $fileId, int $version, string $storedName, string $originalName, int $size, int $userId): void
    {
        Database::insert('archive_file_versions', [
            'file_id' => $fileId,
            'version' => $version,
            'stored_name' => $storedName,
            'original_name' => $originalName,
            'size' => $size,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forFile(int $fileId): array
    {
        return Database::select(
            'SELECT v.*, u.name AS user_name
               FROM archive_file_versions v
               LEFT JOIN users u ON u.id = v.created_by
              WHERE v.file_id = :id
              ORDER BY v.version DESC',
            ['id' => $fileId]
        );
    }
}

<?php

namespace Modules\Documents\Models;

use App\Core\Database;

class DocumentLog
{
    public static function add(int $documentId, ?int $userId, string $action, string $description): void
    {
        Database::insert('documents_logs', [
            'document_id' => $documentId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forDocument(int $documentId): array
    {
        return Database::select(
            'SELECT l.*, u.name AS user_name
               FROM documents_logs l
               LEFT JOIN users u ON u.id = l.user_id
              WHERE l.document_id = :id
              ORDER BY l.id DESC',
            ['id' => $documentId]
        );
    }
}

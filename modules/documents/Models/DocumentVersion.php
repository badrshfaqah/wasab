<?php

namespace Modules\Documents\Models;

use App\Core\Database;

/** لقطات محتوى المستند: تُحفظ الحالة السابقة قبل كل تعديل، وتُتيح الاستعادة. */
class DocumentVersion
{
    public static function forDocument(int $documentId): array
    {
        return Database::select(
            'SELECT v.id, v.version_no, v.title, v.saved_by, v.created_at, u.name AS saved_by_name
               FROM documents_versions v
               LEFT JOIN users u ON u.id = v.saved_by
              WHERE v.document_id = :id
              ORDER BY v.version_no DESC',
            ['id' => $documentId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM documents_versions WHERE id = :id', ['id' => $id]);
    }

    private static function nextVersionNo(int $documentId): int
    {
        $row = Database::first(
            'SELECT MAX(version_no) AS mx FROM documents_versions WHERE document_id = :id',
            ['id' => $documentId]
        );
        return (int) ($row['mx'] ?? 0) + 1;
    }

    /** يحفظ لقطة من الحالة الحالية للمستند (قبل تطبيق تعديل جديد). */
    public static function snapshot(array $document, ?int $userId): int
    {
        return Database::insert('documents_versions', [
            'document_id' => (int) $document['id'],
            'version_no' => self::nextVersionNo((int) $document['id']),
            'title' => (string) $document['title'],
            'content' => $document['content'] ?? null,
            'saved_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function countForDocument(int $documentId): int
    {
        return Database::count('documents_versions', 'document_id = :id', ['id' => $documentId]);
    }
}

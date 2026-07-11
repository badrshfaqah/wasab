<?php

namespace Modules\Archive\Models;

use App\Core\Database;

class ArchiveTag
{
    public static function forCompany(int $companyId): array
    {
        return Database::select('SELECT * FROM archive_tags WHERE company_id = :c ORDER BY name', ['c' => $companyId]);
    }

    public static function forFile(int $fileId): array
    {
        return Database::select(
            'SELECT t.* FROM archive_tags t
               JOIN archive_file_tags ft ON ft.tag_id = t.id
              WHERE ft.file_id = :id
              ORDER BY t.name',
            ['id' => $fileId]
        );
    }

    /** يحوّل قائمة أسماء وسوم (نصية) إلى معرّفات، وينشئ أي وسم غير موجود مسبقاً لهذه الشركة. */
    public static function findOrCreateIds(int $companyId, array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '' || mb_strlen($name) > 60) {
                continue;
            }
            $row = Database::first('SELECT id FROM archive_tags WHERE company_id = :c AND name = :n', ['c' => $companyId, 'n' => $name]);
            if (!$row) {
                $id = Database::insert('archive_tags', ['company_id' => $companyId, 'name' => $name, 'created_at' => date('Y-m-d H:i:s')]);
            } else {
                $id = (int) $row['id'];
            }
            $ids[] = $id;
        }
        return array_unique($ids);
    }

    public static function syncForFile(int $fileId, array $tagIds): void
    {
        Database::delete('archive_file_tags', 'file_id = :id', ['id' => $fileId]);
        foreach (array_unique(array_map('intval', $tagIds)) as $tagId) {
            if ($tagId > 0) {
                Database::insert('archive_file_tags', ['file_id' => $fileId, 'tag_id' => $tagId]);
            }
        }
    }
}

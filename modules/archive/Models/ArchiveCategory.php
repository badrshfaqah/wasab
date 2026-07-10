<?php

namespace Modules\Archive\Models;

use App\Core\Database;

class ArchiveCategory
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM archive_categories WHERE id = :id', ['id' => $id]);
    }

    /** كل تصنيفات الشركة بترتيب شجري (الجذور أولاً، ثم أبناء كل واحد بعده) لعرضها كقائمة متداخلة. */
    public static function tree(int $companyId): array
    {
        $all = Database::select(
            'SELECT * FROM archive_categories WHERE company_id = :c ORDER BY name',
            ['c' => $companyId]
        );

        $byParent = [];
        foreach ($all as $row) {
            $byParent[$row['parent_id'] ?? 0][] = $row;
        }

        $ordered = [];
        $walk = function ($parentId, $depth) use (&$walk, &$ordered, $byParent) {
            foreach ($byParent[$parentId] ?? [] as $row) {
                $row['depth'] = $depth;
                $ordered[] = $row;
                $walk((int) $row['id'], $depth + 1);
            }
        };
        $walk(0, 0);

        return $ordered;
    }

    public static function childrenIds(int $categoryId): array
    {
        $ids = [];
        $queue = [$categoryId];
        while ($queue) {
            $current = array_shift($queue);
            $children = Database::select('SELECT id FROM archive_categories WHERE parent_id = :p', ['p' => $current]);
            foreach ($children as $c) {
                $ids[] = (int) $c['id'];
                $queue[] = (int) $c['id'];
            }
        }
        return $ids;
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('archive_categories', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('archive_categories', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('archive_categories', 'id = :id', ['id' => $id]);
    }

    public static function hasChildren(int $id): bool
    {
        return Database::count('archive_categories', 'parent_id = :id', ['id' => $id]) > 0;
    }

    public static function hasFiles(int $id): bool
    {
        return Database::count('archive_files', 'category_id = :id', ['id' => $id]) > 0;
    }

    public static function setAccessUsers(int $categoryId, array $userIds): void
    {
        Database::delete('archive_category_users', 'category_id = :id', ['id' => $categoryId]);
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0) {
                Database::insert('archive_category_users', ['category_id' => $categoryId, 'user_id' => $uid]);
            }
        }
    }

    public static function accessUserIds(int $categoryId): array
    {
        return array_column(
            Database::select('SELECT user_id FROM archive_category_users WHERE category_id = :id', ['id' => $categoryId]),
            'user_id'
        );
    }
}

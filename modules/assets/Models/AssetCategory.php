<?php

namespace Modules\Assets\Models;

use App\Core\Database;

class AssetCategory
{
    public static function forCompany(int $companyId): array
    {
        return Database::select(
            'SELECT c.*, (SELECT COUNT(*) FROM assets_assets a WHERE a.category_id = c.id) AS assets_count
               FROM assets_categories c WHERE c.company_id = :c ORDER BY c.name',
            ['c' => $companyId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM assets_categories WHERE id = :id', ['id' => $id]);
    }

    public static function create(int $companyId, string $name): int
    {
        return Database::insert('assets_categories', [
            'company_id' => $companyId,
            'name' => mb_substr($name, 0, 120),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function delete(int $id): void
    {
        // فك ربط الأصول قبل الحذف (لا تُحذف الأصول نفسها)
        Database::update('assets_assets', ['category_id' => null], 'category_id = :id', ['id' => $id]);
        Database::delete('assets_categories', 'id = :id', ['id' => $id]);
    }
}

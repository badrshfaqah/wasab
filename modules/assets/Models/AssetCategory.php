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

    public static function create(int $companyId, string $name, array $fields = []): int
    {
        return Database::insert('assets_categories', [
            'company_id' => $companyId,
            'name' => mb_substr($name, 0, 120),
            'fields_json' => $fields ? json_encode(array_values($fields), JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** أسماء الحقول المخصصة لتصنيف (من fields_json). */
    public static function fields(?array $category): array
    {
        if (!$category || empty($category['fields_json'])) {
            return [];
        }
        $decoded = json_decode((string) $category['fields_json'], true);
        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded), fn ($f) => $f !== '')) : [];
    }

    /** تحديث أسماء الحقول المخصصة لتصنيف. */
    public static function updateFields(int $id, array $fields): void
    {
        Database::update('assets_categories', [
            'fields_json' => $fields ? json_encode(array_values($fields), JSON_UNESCAPED_UNICODE) : null,
        ], 'id = :id', ['id' => $id]);
    }

    /** يحوّل نص "حقل1، حقل2" إلى مصفوفة أسماء نظيفة (حد 10 حقول، 60 حرفاً للاسم). */
    public static function parseFieldsInput(string $raw): array
    {
        $parts = preg_split('/[,،]/u', $raw) ?: [];
        $fields = [];
        foreach ($parts as $p) {
            $p = mb_substr(trim($p), 0, 60);
            if ($p !== '' && !in_array($p, $fields, true)) {
                $fields[] = $p;
            }
            if (count($fields) >= 10) {
                break;
            }
        }
        return $fields;
    }

    public static function delete(int $id): void
    {
        // فك ربط الأصول قبل الحذف (لا تُحذف الأصول نفسها)
        Database::update('assets_assets', ['category_id' => null], 'category_id = :id', ['id' => $id]);
        Database::delete('assets_categories', 'id = :id', ['id' => $id]);
    }
}

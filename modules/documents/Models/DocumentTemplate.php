<?php

namespace Modules\Documents\Models;

use App\Core\Database;

class DocumentTemplate
{
    public static function forCompany(int $companyId): array
    {
        return Database::select(
            'SELECT * FROM documents_templates WHERE company_id = :c ORDER BY name',
            ['c' => $companyId]
        );
    }

    /**
     * القوالب المتاحة لمستخدم: قوالبه + المشارَكة معه + قوالب الشركة العامة
     * (created_by IS NULL). المشارَك يحمل اسم صاحبه «(مشاركة من فلان)».
     */
    public static function usableBy(int $userId, int $companyId): array
    {
        return Database::select(
            "SELECT * FROM (
                SELECT t.*, NULL AS owner_name FROM documents_templates t
                 WHERE t.company_id = :c AND (t.created_by = :u OR t.created_by IS NULL)
                UNION ALL
                SELECT t.*, u.name AS owner_name
                  FROM documents_template_shares sh
                  JOIN documents_templates t ON t.id = sh.template_id
                  JOIN users u ON u.id = t.created_by
                 WHERE sh.user_id = :u2 AND t.company_id = :c2
             ) x ORDER BY (x.owner_name IS NOT NULL), x.name",
            ['c' => $companyId, 'u' => $userId, 'u2' => $userId, 'c2' => $companyId]
        );
    }

    /** قالب يحق للمستخدم استخدامه: ملكه، أو قالب شركة عام، أو مشارَك معه. */
    public static function findUsableBy(int $id, int $userId, int $companyId): ?array
    {
        return Database::first(
            'SELECT t.* FROM documents_templates t
              WHERE t.id = :id AND t.company_id = :c AND (t.created_by = :u OR t.created_by IS NULL
                 OR EXISTS (SELECT 1 FROM documents_template_shares sh WHERE sh.template_id = t.id AND sh.user_id = :u2))',
            ['id' => $id, 'c' => $companyId, 'u' => $userId, 'u2' => $userId]
        );
    }

    /** معرّفات من شُورك معهم قالب معيّن. */
    public static function shareUserIds(int $templateId): array
    {
        return array_map(
            fn ($r) => (int) $r['user_id'],
            Database::select('SELECT user_id FROM documents_template_shares WHERE template_id = :t', ['t' => $templateId])
        );
    }

    /** استبدال قائمة المشارَك معهم لقالب. */
    public static function setShares(int $templateId, array $userIds): void
    {
        Database::delete('documents_template_shares', 'template_id = :t', ['t' => $templateId]);
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0) {
                Database::insert('documents_template_shares', [
                    'template_id' => $templateId,
                    'user_id' => $uid,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM documents_templates WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('documents_templates', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('documents_templates', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('documents_templates', 'id = :id', ['id' => $id]);
    }
}

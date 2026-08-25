<?php

namespace App\Core;

/**
 * الأختام. لكل مستخدم أختامه الشخصية (user_id) يرفعها من ملفه الشخصي ويشاركها مع
 * من يشاء، وتبقى أختام الشركة القديمة (user_id NULL) مكتبةً يديرها المدراء.
 * الصور مخزّنة في storage/uploads/stamps/{companyId}/ وتُخدم عبر /media/stamps/.
 */
class CompanyStamp
{
    public static function forCompany(int $companyId): array
    {
        return Database::select(
            'SELECT * FROM company_stamps WHERE company_id = :c ORDER BY name',
            ['c' => $companyId]
        );
    }

    /** مكتبة أختام الشركة العامة فقط (بلا الأختام الشخصية لأصحابها). */
    public static function companyLibrary(int $companyId): array
    {
        return Database::select(
            'SELECT * FROM company_stamps WHERE company_id = :c AND user_id IS NULL ORDER BY name',
            ['c' => $companyId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM company_stamps WHERE id = :id', ['id' => $id]);
    }

    /** ختم يخصّ الشركة (تحقق عزل قبل الاستخدام/الحذف). */
    public static function findForCompany(int $id, int $companyId): ?array
    {
        return Database::first(
            'SELECT * FROM company_stamps WHERE id = :id AND company_id = :c',
            ['id' => $id, 'c' => $companyId]
        );
    }

    public static function create(int $companyId, string $name, string $image, ?int $userId = null): int
    {
        return Database::insert('company_stamps', [
            'company_id' => $companyId,
            'user_id' => $userId,
            'name' => mb_substr($name, 0, 120),
            'image' => $image,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** أختام مستخدم الشخصية (لعرضها في ملفه الشخصي مع خيارات المشاركة). */
    public static function forUser(int $userId): array
    {
        return Database::select(
            'SELECT * FROM company_stamps WHERE user_id = :u ORDER BY id DESC',
            ['u' => $userId]
        );
    }

    /**
     * الأختام المتاحة لمستخدم للاستخدام: أختامه + المشارَكة معه + (للمدير) مكتبة
     * الشركة القديمة. المشارَك يحمل اسم صاحبه «(مشاركة من فلان)».
     */
    public static function usableBy(int $userId, int $companyId, bool $isManager = false): array
    {
        $legacy = $isManager ? ' OR (s.company_id = :c2 AND s.user_id IS NULL)' : '';
        $params = ['u' => $userId, 'u2' => $userId, 'c' => $companyId];
        if ($isManager) {
            $params['c2'] = $companyId;
        }
        return Database::select(
            "SELECT * FROM (
                SELECT s.*, NULL AS owner_name FROM company_stamps s
                 WHERE s.company_id = :c AND (s.user_id = :u{$legacy})
                UNION ALL
                SELECT s.*, u.name AS owner_name
                  FROM user_stamp_shares sh
                  JOIN company_stamps s ON s.id = sh.stamp_id
                  JOIN users u ON u.id = s.user_id
                 WHERE sh.user_id = :u2
             ) t ORDER BY (t.owner_name IS NOT NULL), t.id DESC",
            $params
        );
    }

    /** ختم يحق للمستخدم استخدامه: ملكه، أو مشارَك معه، أو من مكتبة الشركة للمدير. */
    public static function findUsableBy(int $id, int $userId, int $companyId, bool $isManager = false): ?array
    {
        $legacy = $isManager ? ' OR s.user_id IS NULL' : '';
        return Database::first(
            "SELECT s.* FROM company_stamps s
              WHERE s.id = :id AND s.company_id = :c AND (s.user_id = :u{$legacy}
                 OR EXISTS (SELECT 1 FROM user_stamp_shares sh WHERE sh.stamp_id = s.id AND sh.user_id = :u2))",
            ['id' => $id, 'c' => $companyId, 'u' => $userId, 'u2' => $userId]
        );
    }

    /** معرّفات من شُورك معهم ختم معيّن. */
    public static function shareUserIds(int $stampId): array
    {
        return array_map(
            fn ($r) => (int) $r['user_id'],
            Database::select('SELECT user_id FROM user_stamp_shares WHERE stamp_id = :s', ['s' => $stampId])
        );
    }

    /** استبدال قائمة المشارَك معهم لختم. */
    public static function setShares(int $stampId, array $userIds): void
    {
        Database::delete('user_stamp_shares', 'stamp_id = :s', ['s' => $stampId]);
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0) {
                Database::insert('user_stamp_shares', [
                    'stamp_id' => $stampId,
                    'user_id' => $uid,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public static function delete(int $id, int $companyId): void
    {
        Database::delete('company_stamps', 'id = :id AND company_id = :c', ['id' => $id, 'c' => $companyId]);
    }

    /** رابط عرض صورة الختم (يمرّ عبر MediaController المحمي). */
    public static function imageUrl(array $stamp): string
    {
        return route('/media/stamps/' . (int) $stamp['company_id'] . '/' . $stamp['image']);
    }
}

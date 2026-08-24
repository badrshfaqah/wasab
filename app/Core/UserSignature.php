<?php

namespace App\Core;

/**
 * تواقيع المستخدم الشخصية. كل مستخدم يرفع توقيعه (أو أكثر) من ملفه الشخصي، ويختار
 * منها عند توقيع مستند أو توليد خطاب - فلا يوقّع أحد بتوقيع غيره. الصور مخزّنة في
 * storage/uploads/signatures/{companyId}/ وتُخدم عبر /media/signatures/{cid}/{file}.
 */
class UserSignature
{
    /** تواقيع مستخدم معيّن (الأحدث أولاً). */
    public static function forUser(int $userId): array
    {
        return Database::select(
            'SELECT * FROM user_signatures WHERE user_id = :u ORDER BY id DESC',
            ['u' => $userId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM user_signatures WHERE id = :id', ['id' => $id]);
    }

    /** توقيع يخصّ هذا المستخدم تحديداً (تحقق ملكية قبل الاستخدام/الحذف). */
    public static function findForUser(int $id, int $userId): ?array
    {
        return Database::first(
            'SELECT * FROM user_signatures WHERE id = :id AND user_id = :u',
            ['id' => $id, 'u' => $userId]
        );
    }

    public static function create(int $userId, ?int $companyId, string $name, string $image): int
    {
        return Database::insert('user_signatures', [
            'user_id' => $userId,
            'company_id' => $companyId,
            'name' => mb_substr($name, 0, 120),
            'image' => $image,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function delete(int $id, int $userId): void
    {
        Database::delete('user_signatures', 'id = :id AND user_id = :u', ['id' => $id, 'u' => $userId]);
    }

    /**
     * التواقيع المتاحة لمستخدم للاستخدام الفعلي: تواقيعه + ما شاركه معه أصحابه
     * (توكيل توقيع). المشارَك يحمل اسم صاحبه ليظهر في القوائم «(مشاركة من فلان)».
     */
    public static function usableBy(int $userId, ?int $companyId = null): array
    {
        return Database::select(
            "SELECT * FROM (
                SELECT s.*, NULL AS owner_name FROM user_signatures s WHERE s.user_id = :u
                UNION ALL
                SELECT s.*, u.name AS owner_name
                  FROM user_signature_shares sh
                  JOIN user_signatures s ON s.id = sh.signature_id
                  JOIN users u ON u.id = s.user_id
                 WHERE sh.user_id = :u2" . ($companyId ? ' AND s.company_id = :c' : '') . "
             ) t ORDER BY (t.owner_name IS NOT NULL), t.id DESC",
            array_merge(['u' => $userId, 'u2' => $userId], $companyId ? ['c' => $companyId] : [])
        );
    }

    /** توقيع يحق لهذا المستخدم استخدامه: ملكه أو مشارَك معه. */
    public static function findUsableBy(int $id, int $userId): ?array
    {
        return Database::first(
            'SELECT s.* FROM user_signatures s
              WHERE s.id = :id AND (s.user_id = :u
                 OR EXISTS (SELECT 1 FROM user_signature_shares sh WHERE sh.signature_id = s.id AND sh.user_id = :u2))',
            ['id' => $id, 'u' => $userId, 'u2' => $userId]
        );
    }

    /** معرّفات من شُورك معهم توقيع معيّن. */
    public static function shareUserIds(int $signatureId): array
    {
        return array_map(
            fn ($r) => (int) $r['user_id'],
            Database::select('SELECT user_id FROM user_signature_shares WHERE signature_id = :s', ['s' => $signatureId])
        );
    }

    /** استبدال قائمة المشارَك معهم لتوقيع (يملكه المستخدم حصراً). */
    public static function setShares(int $signatureId, array $userIds): void
    {
        Database::delete('user_signature_shares', 'signature_id = :s', ['s' => $signatureId]);
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0) {
                Database::insert('user_signature_shares', [
                    'signature_id' => $signatureId,
                    'user_id' => $uid,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /** رابط عرض صورة التوقيع (يمرّ عبر MediaController المحمي). */
    public static function imageUrl(array $signature): string
    {
        return route('/media/signatures/' . (int) $signature['company_id'] . '/' . $signature['image']);
    }
}

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

    /** رابط عرض صورة التوقيع (يمرّ عبر MediaController المحمي). */
    public static function imageUrl(array $signature): string
    {
        return route('/media/signatures/' . (int) $signature['company_id'] . '/' . $signature['image']);
    }
}

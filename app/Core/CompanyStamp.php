<?php

namespace App\Core;

/**
 * أختام الشركة. يديرها المدير (إضافة/حذف) من صفحة الأختام، وتُربط بقوالب المستندات
 * والنماذج فيُطبَّق ختم القالب تلقائياً على أي مستند مُولَّد منه. الصور مخزّنة في
 * storage/uploads/stamps/{companyId}/ وتُخدم عبر /media/stamps/{cid}/{file}.
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

    public static function create(int $companyId, string $name, string $image): int
    {
        return Database::insert('company_stamps', [
            'company_id' => $companyId,
            'name' => mb_substr($name, 0, 120),
            'image' => $image,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
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

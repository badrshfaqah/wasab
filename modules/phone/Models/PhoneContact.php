<?php

namespace Modules\Phone\Models;

use App\Core\Database;

class PhoneContact
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM phone_contacts WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('phone_contacts', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('phone_contacts', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('phone_contacts', 'id = :id', ['id' => $id]);
    }

    /**
     * دليل جهات الاتصال المرئي للمستخدم الحالي: كل الجهات العامة + جهاته الخاصة فقط
     * (مدير الشركة/النظام يرى كل الخاصة أيضاً لأغراض الإشراف على الدليل). الرقم
     * الداخلي يُقرأ حياً من phone_users بدلاً من تخزينه لتفادي تعارضه لاحقاً مع
     * تحويلة الموظف الفعلية إن تغيّرت.
     */
    public static function forUser(int $companyId, int $userId, bool $isCompanyAdminOrAbove, ?string $search = null): array
    {
        $where = 'c.company_id = :company_id AND (c.visibility = "public" OR c.created_by = :user_id';
        $params = ['company_id' => $companyId, 'user_id' => $userId];
        if ($isCompanyAdminOrAbove) {
            $where .= ' OR c.visibility = "private"';
        }
        $where .= ')';

        if ($search) {
            $where .= ' AND (c.name LIKE :q1 OR c.phone_number LIKE :q2 OR u.name LIKE :q3)';
            $needle = '%' . $search . '%';
            $params['q1'] = $needle;
            $params['q2'] = $needle;
            $params['q3'] = $needle;
        }

        return Database::select(
            "SELECT c.*, u.name AS linked_user_name, pu.extension AS linked_extension, pu.enabled AS linked_enabled,
                    creator.name AS creator_name
               FROM phone_contacts c
               LEFT JOIN users u ON u.id = c.linked_user_id
               LEFT JOIN phone_users pu ON pu.user_id = c.linked_user_id
               LEFT JOIN users creator ON creator.id = c.created_by
              WHERE {$where}
              ORDER BY c.type, COALESCE(u.name, c.name)",
            $params
        );
    }

    /** موظفو نفس الشركة الذين لديهم تحويلة مُفعّلة - لاختيار جهة اتصال داخلية منهم. */
    public static function companyExtensionUsers(int $companyId): array
    {
        return Database::select(
            'SELECT u.id, u.name, pu.extension
               FROM users u
               JOIN phone_users pu ON pu.user_id = u.id
              WHERE u.company_id = :c AND u.status = "active" AND pu.enabled = 1
              ORDER BY u.name',
            ['c' => $companyId]
        );
    }
}

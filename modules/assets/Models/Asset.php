<?php

namespace Modules\Assets\Models;

use App\Core\Database;

class Asset
{
    public const STATUSES = ['available', 'assigned', 'maintenance', 'retired', 'lost'];

    public static function statusLabels(): array
    {
        return [
            'available' => 'متاح',
            'assigned' => 'بعهدة',
            'maintenance' => 'صيانة',
            'retired' => 'خارج الخدمة',
            'lost' => 'مفقود',
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT a.*, c.name AS category_name
               FROM assets_assets a
               LEFT JOIN assets_categories c ON c.id = a.category_id
              WHERE a.id = :id',
            ['id' => $id]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('assets_assets', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('assets_assets', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('assets_assets', 'id = :id', ['id' => $id]);
    }

    private static function buildFilters(int $companyId, array $filters): array
    {
        $where = ['a.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        if (!empty($filters['status'])) {
            $where[] = 'a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'a.category_id = :cat';
            $params['cat'] = (int) $filters['category_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(a.name LIKE :q OR a.asset_code LIKE :q OR a.serial_number LIKE :q OR a.current_holder_name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    public static function paginate(int $companyId, int $page, int $perPage, array $filters = []): array
    {
        [$whereSql, $params] = self::buildFilters($companyId, $filters);
        $total = (int) (Database::first("SELECT COUNT(*) AS c FROM assets_assets a WHERE {$whereSql}", $params)['c'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT a.*, c.name AS category_name
               FROM assets_assets a
               LEFT JOIN assets_categories c ON c.id = a.category_id
              WHERE {$whereSql}
              ORDER BY a.id DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** أصول متاحة للإسناد (غير مسندة وليست خارج الخدمة/مفقودة). */
    public static function assignable(int $companyId): array
    {
        return Database::select(
            "SELECT id, name, asset_code, serial_number FROM assets_assets
              WHERE company_id = :c AND status = 'available'
              ORDER BY name",
            ['c' => $companyId]
        );
    }

    /** العهد الحالية المسندة لمستخدم نظام معيّن (عبر user مباشرة أو employee مربوط به). */
    public static function currentlyHeldByUser(int $companyId, int $userId): array
    {
        // الفرع الخاص بالملف الوظيفي يُضاف فقط عند تفعيل تلك الإضافة (وإلا فجدولها
        // غير موجود، فلا نُدرج استعلاماً يشير إليه ويكسر الصفحة).
        $employeeClause = '';
        $params = ['c' => $companyId, 'uid' => $userId];
        if (\App\Core\ModuleManager::isActive('employees')) {
            $employeeClause = "OR (a.current_holder_type = 'employee' AND a.current_holder_ref IN (
                SELECT id FROM employees_profiles WHERE company_id = :c2 AND linked_user_id = :uid2
            ))";
            $params['c2'] = $companyId;
            $params['uid2'] = $userId;
        }

        return Database::select(
            "SELECT a.*, c.name AS category_name
               FROM assets_assets a
               LEFT JOIN assets_categories c ON c.id = a.category_id
              WHERE a.company_id = :c AND a.status = 'assigned'
                AND (
                    (a.current_holder_type = 'user' AND a.current_holder_ref = :uid)
                    {$employeeClause}
                )
              ORDER BY a.assigned_at DESC",
            $params
        );
    }

    public static function countByStatus(int $companyId, string $status): int
    {
        return Database::count('assets_assets', 'company_id = :c AND status = :s', ['c' => $companyId, 's' => $status]);
    }

    public static function warrantyEvents(int $companyId, string $fromDate, string $toDate): array
    {
        return Database::select(
            "SELECT id, name, warranty_expiry FROM assets_assets
              WHERE company_id = :c AND warranty_expiry IS NOT NULL
                AND warranty_expiry BETWEEN :from AND :to",
            ['c' => $companyId, 'from' => $fromDate, 'to' => $toDate]
        );
    }
}

<?php

namespace Modules\Employees\Models;

use App\Core\Database;

class Employee
{
    public static function statusLabels(): array
    {
        return [
            'active' => 'نشط',
            'on_leave' => 'إجازة',
            'suspended' => 'موقوف',
            'terminated' => 'منتهي الخدمة',
        ];
    }

    public static function employmentTypeLabels(): array
    {
        return [
            'full_time' => 'دوام كامل',
            'part_time' => 'دوام جزئي',
            'contract' => 'عقد مؤقت',
        ];
    }

    public static function genderLabels(): array
    {
        return ['male' => 'ذكر', 'female' => 'أنثى'];
    }

    public static function maritalStatusLabels(): array
    {
        return [
            'single' => 'أعزب',
            'married' => 'متزوج',
            'divorced' => 'مطلّق',
            'widowed' => 'أرمل',
        ];
    }

    public static function dependentRelationLabels(): array
    {
        return ['spouse' => 'زوج/زوجة', 'child' => 'ابن/ابنة', 'other' => 'أخرى'];
    }

    /** أنواع أحداث السجل الوظيفي المعروفة - أي نوع آخر يُعرض كنص خام (تسجيل يدوي حر). */
    public static function timelineEventLabels(): array
    {
        return [
            'hire' => 'تعيين',
            'status_change' => 'تغيير حالة',
            'promotion' => 'ترقية',
            'department_change' => 'نقل قسم',
            'salary_change' => 'تغيير راتب',
            'warning' => 'إنذار',
            'termination' => 'إنهاء خدمة',
            'note' => 'ملاحظة',
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM employees_profiles WHERE id = :id', ['id' => $id]);
    }

    public static function findByLinkedUser(int $companyId, int $userId): ?array
    {
        return Database::first(
            'SELECT * FROM employees_profiles WHERE company_id = :c AND linked_user_id = :u',
            ['c' => $companyId, 'u' => $userId]
        );
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('employees_profiles', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('employees_profiles', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('employees_profiles', 'id = :id', ['id' => $id]);
    }

    public static function paginate(int $companyId, array $filters, int $page, int $perPage): array
    {
        [$where, $params] = self::buildFilters($companyId, $filters);

        $total = Database::first("SELECT COUNT(*) AS c FROM employees_profiles WHERE {$where}", $params)['c'] ?? 0;

        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT * FROM employees_profiles WHERE {$where} ORDER BY full_name LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => (int) $total];
    }

    private static function buildFilters(int $companyId, array $filters): array
    {
        $where = 'company_id = :company_id';
        $params = ['company_id' => $companyId];

        if (!empty($filters['status'])) {
            $where .= ' AND status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (full_name LIKE :q OR job_title LIKE :q2 OR department LIKE :q3)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
            $params['q3'] = '%' . $filters['q'] . '%';
        }

        return [$where, $params];
    }

    /** لقائمة "المدير المباشر" بنموذج الإضافة/التعديل - كل موظفي الشركة عدا الملف نفسه (تفادي جعله مديراً لنفسه). */
    public static function selectableManagers(int $companyId, ?int $excludeId = null): array
    {
        $where = 'company_id = :c';
        $params = ['c' => $companyId];
        if ($excludeId) {
            $where .= ' AND id != :ex';
            $params['ex'] = $excludeId;
        }
        return Database::select("SELECT id, full_name FROM employees_profiles WHERE {$where} ORDER BY full_name", $params);
    }

    public static function countByStatus(int $companyId, string $status): int
    {
        return Database::count('employees_profiles', 'company_id = :c AND status = :s', ['c' => $companyId, 's' => $status]);
    }

    /** لمزوّد التقويم: عقود تنتهي أو شهادات/رخص تنتهي ضمن نطاق تاريخ معيّن. */
    public static function forCalendarRange(int $companyId, string $fromDate, string $toDate): array
    {
        $contracts = Database::select(
            "SELECT id, full_name, contract_end_date AS due_date, 'عقد' AS kind
               FROM employees_profiles
              WHERE company_id = :c AND status != 'terminated' AND contract_end_date BETWEEN :from AND :to",
            ['c' => $companyId, 'from' => $fromDate, 'to' => $toDate]
        );

        $licenses = Database::select(
            "SELECT id, full_name, driving_license_expiry AS due_date, 'رخصة قيادة' AS kind
               FROM employees_profiles
              WHERE company_id = :c AND status != 'terminated' AND driving_license_expiry BETWEEN :from AND :to",
            ['c' => $companyId, 'from' => $fromDate, 'to' => $toDate]
        );

        return array_merge($contracts, $licenses);
    }
}

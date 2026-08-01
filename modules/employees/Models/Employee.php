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
    /**
     * الوثائق القابلة للانتهاء على مستوى الملف الوظيفي (عمود => [تسمية، رمز]).
     * قائمة بيضاء ثابتة - أسماء الأعمدة لا تأتي من المستخدم إطلاقاً.
     */
    public static function expirySources(): array
    {
        return [
            'iqama_expiry' => ['label' => 'الإقامة', 'icon' => '🪪'],
            'passport_expiry' => ['label' => 'الجواز', 'icon' => '📗'],
            'driving_license_expiry' => ['label' => 'رخصة القيادة', 'icon' => '🚗'],
            'health_cert_expiry' => ['label' => 'الشهادة الصحية', 'icon' => '🩺'],
            'insurance_expiry' => ['label' => 'التأمين الطبي', 'icon' => '🏥'],
            'contract_end_date' => ['label' => 'العقد', 'icon' => '📄'],
            'probation_end_date' => ['label' => 'فترة التجربة', 'icon' => '⏳'],
        ];
    }

    /** يبني استعلام UNION موحّداً لكل مصادر الانتهاء حتى تاريخ حدّي. */
    private static function expiryUnionSql(): string
    {
        $parts = [];
        foreach (self::expirySources() as $col => $meta) {
            // الاسم من قائمة بيضاء، والتسميات ثابتة - لا مدخلات مستخدم في SQL
            $label = str_replace("'", '', $meta['label']);
            $icon = $meta['icon'];
            $parts[] = "SELECT id AS employee_id, full_name, '{$label}' AS kind, '{$icon}' AS icon, `{$col}` AS expiry_date
                          FROM employees_profiles
                         WHERE company_id = :c AND status != 'terminated'
                           AND `{$col}` IS NOT NULL AND `{$col}` <= :cutoff";
        }
        // الشهادات/الدورات لها جدول منفصل
        $parts[] = "SELECT ec.employee_id, ep.full_name, CONCAT('شهادة: ', ec.title) AS kind, '🎓' AS icon, ec.expiry_date
                      FROM employees_certifications ec
                      JOIN employees_profiles ep ON ep.id = ec.employee_id
                     WHERE ep.company_id = :c AND ep.status != 'terminated'
                       AND ec.expiry_date IS NOT NULL AND ec.expiry_date <= :cutoff";
        return implode("\nUNION ALL\n", $parts);
    }

    /**
     * كل الوثائق التي انتهت أو ستنتهي خلال $withinDays يوماً، لكل موظفي الشركة،
     * مرتّبة بالأقرب انتهاءً (المنتهية أولاً). تُحسب الأيام المتبقية في PHP.
     */
    public static function expiringDocuments(int $companyId, int $withinDays = 60): array
    {
        $cutoff = date('Y-m-d', strtotime("+{$withinDays} days"));
        $sql = self::expiryUnionSql() . "\nORDER BY expiry_date ASC";
        $rows = Database::select($sql, ['c' => $companyId, 'cutoff' => $cutoff]);

        $today = new \DateTime('today');
        foreach ($rows as &$row) {
            $exp = new \DateTime($row['expiry_date']);
            $row['days_left'] = (int) $today->diff($exp)->format('%r%a');
        }
        return $rows;
    }

    /** عدد الوثائق المنتهية أو التي ستنتهي قريباً (لبطاقة الرئيسية). */
    public static function countExpiringDocuments(int $companyId, int $withinDays = 60): int
    {
        $cutoff = date('Y-m-d', strtotime("+{$withinDays} days"));
        $sql = "SELECT COUNT(*) AS c FROM (" . self::expiryUnionSql() . ") t";
        return (int) (Database::first($sql, ['c' => $companyId, 'cutoff' => $cutoff])['c'] ?? 0);
    }

    public static function forCalendarRange(int $companyId, string $fromDate, string $toDate): array
    {
        $events = [];
        foreach (self::expirySources() as $col => $meta) {
            $rows = Database::select(
                "SELECT id, full_name, `{$col}` AS due_date
                   FROM employees_profiles
                  WHERE company_id = :c AND status != 'terminated' AND `{$col}` BETWEEN :from AND :to",
                ['c' => $companyId, 'from' => $fromDate, 'to' => $toDate]
            );
            foreach ($rows as $r) {
                $events[] = ['id' => $r['id'], 'full_name' => $r['full_name'], 'due_date' => $r['due_date'], 'kind' => $meta['label']];
            }
        }
        return $events;
    }
}

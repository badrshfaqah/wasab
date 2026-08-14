<?php

namespace Modules\Employees\Models;

use App\Core\Database;

/**
 * طلبات الإجازات والأذونات: يقدّمها الموظف (أو مديره نيابةً عنه)، ويعتمدها أو
 * يرفضها من يملك صلاحية الإدارة. الإجازة السنوية المعتمدة تُخصم من رصيد الموظف.
 */
class EmployeeLeave
{
    public static function typeLabels(): array
    {
        return [
            'annual' => 'إجازة سنوية',
            'sick' => 'إجازة مرضية',
            'hours' => 'إذن ساعات',
            'unpaid' => 'إجازة بدون راتب',
            'other' => 'أخرى',
        ];
    }

    public static function statusLabels(): array
    {
        return ['pending' => 'بانتظار الموافقة', 'approved' => 'معتمدة', 'rejected' => 'مرفوضة'];
    }

    /** عدد الأيام شاملاً الطرفين (اليوم الواحد = 1). */
    public static function daysCount(string $startDate, string $endDate): int
    {
        $from = new \DateTime($startDate);
        $to = new \DateTime($endDate);
        return max(1, (int) $from->diff($to)->format('%a') + 1);
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT l.*, e.full_name, e.linked_user_id, e.annual_leave_balance
               FROM employees_leaves l
               JOIN employees_profiles e ON e.id = l.employee_id
              WHERE l.id = :id',
            ['id' => $id]
        );
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('employees_leaves', $data);
    }

    /** طلبات الشركة (المعلّقة أولاً ثم الأحدث قراراً) مع اسم الموظف ومقرِّر الطلب. */
    public static function forCompany(int $companyId, int $limit = 200): array
    {
        return Database::select(
            "SELECT l.*, e.full_name, e.linked_user_id, u.name AS decided_by_name
               FROM employees_leaves l
               JOIN employees_profiles e ON e.id = l.employee_id
               LEFT JOIN users u ON u.id = l.decided_by
              WHERE l.company_id = :c
              ORDER BY (l.status = 'pending') DESC, l.id DESC
              LIMIT {$limit}",
            ['c' => $companyId]
        );
    }

    /** طلبات موظف واحد (لعرضها له في صفحته). */
    public static function forEmployee(int $employeeId, int $limit = 100): array
    {
        return Database::select(
            "SELECT l.*, u.name AS decided_by_name
               FROM employees_leaves l
               LEFT JOIN users u ON u.id = l.decided_by
              WHERE l.employee_id = :e
              ORDER BY l.id DESC LIMIT {$limit}",
            ['e' => $employeeId]
        );
    }

    public static function countPending(int $companyId): int
    {
        return Database::count('employees_leaves', "company_id = :c AND status = 'pending'", ['c' => $companyId]);
    }

    /** هل لدى الموظف طلب معلّق أو معتمد يتقاطع مع نفس الفترة؟ (منع الازدواج) */
    public static function overlaps(int $employeeId, string $startDate, string $endDate): bool
    {
        return (bool) Database::first(
            "SELECT 1 FROM employees_leaves
              WHERE employee_id = :e AND status IN ('pending','approved')
                AND start_date <= :end AND end_date >= :start LIMIT 1",
            ['e' => $employeeId, 'start' => $startDate, 'end' => $endDate]
        );
    }

    /** اعتماد الطلب وخصم الرصيد للإجازة السنوية (داخل معاملة). */
    public static function approve(array $leave, int $deciderId, ?string $note): void
    {
        Database::update('employees_leaves', [
            'status' => 'approved',
            'decided_by' => $deciderId,
            'decided_at' => date('Y-m-d H:i:s'),
            'decision_note' => $note,
        ], 'id = :id', ['id' => $leave['id']]);

        if ($leave['type'] === 'annual') {
            Database::pdo()->prepare(
                'UPDATE employees_profiles SET annual_leave_balance = annual_leave_balance - ? WHERE id = ?'
            )->execute([(int) $leave['days_count'], (int) $leave['employee_id']]);
        }
    }

    public static function reject(int $id, int $deciderId, ?string $note): void
    {
        Database::update('employees_leaves', [
            'status' => 'rejected',
            'decided_by' => $deciderId,
            'decided_at' => date('Y-m-d H:i:s'),
            'decision_note' => $note,
        ], 'id = :id', ['id' => $id]);
    }

    /** الإجازات المعتمدة المتقاطعة مع نطاق (للتقويم). */
    public static function approvedInRange(int $companyId, string $fromDate, string $toDate): array
    {
        return Database::select(
            "SELECT l.*, e.full_name, e.linked_user_id
               FROM employees_leaves l
               JOIN employees_profiles e ON e.id = l.employee_id
              WHERE l.company_id = :c AND l.status = 'approved'
                AND l.start_date <= :to AND l.end_date >= :from",
            ['c' => $companyId, 'from' => $fromDate, 'to' => $toDate]
        );
    }

    /** عدد الموظفين بإجازة معتمدة تغطي اليوم (أو حالتهم on_leave يدوياً). */
    public static function onLeaveTodayCount(int $companyId): int
    {
        $today = date('Y-m-d');
        return (int) (Database::first(
            "SELECT COUNT(DISTINCT e.id) AS c
               FROM employees_profiles e
               LEFT JOIN employees_leaves l ON l.employee_id = e.id AND l.status = 'approved'
                    AND l.start_date <= :t1 AND l.end_date >= :t2 AND l.type != 'hours'
              WHERE e.company_id = :c AND e.status != 'terminated'
                AND (l.id IS NOT NULL OR e.status = 'on_leave')",
            ['c' => $companyId, 't1' => $today, 't2' => $today]
        )['c'] ?? 0);
    }

    /** عدد الإجازات المعتمدة (أيامها) خلال شهر معيّن - للتقرير الشهري. */
    public static function approvedInMonth(int $companyId, string $yearMonth): array
    {
        $from = $yearMonth . '-01';
        $to = date('Y-m-t', strtotime($from));
        return Database::select(
            "SELECT l.*, e.full_name
               FROM employees_leaves l
               JOIN employees_profiles e ON e.id = l.employee_id
              WHERE l.company_id = :c AND l.status = 'approved'
                AND l.start_date <= :to AND l.end_date >= :from
              ORDER BY l.start_date",
            ['c' => $companyId, 'from' => $from, 'to' => $to]
        );
    }
}

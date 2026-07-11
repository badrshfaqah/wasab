<?php

namespace Modules\Employees\Models;

use App\Core\Database;

class EmployeeCertification
{
    public static function forEmployee(int $employeeId): array
    {
        return Database::select(
            'SELECT * FROM employees_certifications WHERE employee_id = :id ORDER BY issue_date DESC, id DESC',
            ['id' => $employeeId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM employees_certifications WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('employees_certifications', $data);
    }

    public static function delete(int $id): void
    {
        Database::delete('employees_certifications', 'id = :id', ['id' => $id]);
    }

    /** لمزوّد التقويم: شهادات/دورات تنتهي صلاحيتها ضمن نطاق تاريخ معيّن. */
    public static function forCalendarRange(int $companyId, string $fromDate, string $toDate): array
    {
        return Database::select(
            "SELECT c.id, c.title, c.expiry_date AS due_date, e.id AS employee_id, e.full_name
               FROM employees_certifications c
               JOIN employees_profiles e ON e.id = c.employee_id
              WHERE e.company_id = :c AND e.status != 'terminated'
                AND c.expiry_date BETWEEN :from AND :to",
            ['c' => $companyId, 'from' => $fromDate, 'to' => $toDate]
        );
    }
}

<?php

namespace Modules\Employees\Models;

use App\Core\Database;

/** سجل المخالفات والجزاءات التأديبية لموظف (حوكمة الموارد البشرية). */
class EmployeeDisciplinary
{
    public const TYPES = ['verbal', 'written', 'deduction', 'suspension', 'final_warning', 'other'];

    public static function typeLabels(): array
    {
        return [
            'verbal' => 'إنذار شفهي',
            'written' => 'إنذار كتابي',
            'deduction' => 'حسم/خصم',
            'suspension' => 'إيقاف عن العمل',
            'final_warning' => 'إنذار نهائي',
            'other' => 'أخرى',
        ];
    }

    public static function forEmployee(int $employeeId): array
    {
        return Database::select(
            'SELECT d.*, u.name AS issued_by_name
               FROM employees_disciplinary d
               LEFT JOIN users u ON u.id = d.issued_by
              WHERE d.employee_id = :id
              ORDER BY COALESCE(d.action_date, d.incident_date, d.created_at) DESC, d.id DESC',
            ['id' => $employeeId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM employees_disciplinary WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('employees_disciplinary', $data);
    }

    public static function delete(int $id): void
    {
        Database::delete('employees_disciplinary', 'id = :id', ['id' => $id]);
    }

    public static function countForEmployee(int $employeeId): int
    {
        return Database::count('employees_disciplinary', 'employee_id = :id', ['id' => $employeeId]);
    }
}

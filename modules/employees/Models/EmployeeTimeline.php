<?php

namespace Modules\Employees\Models;

use App\Core\Database;

class EmployeeTimeline
{
    public static function forEmployee(int $employeeId): array
    {
        return Database::select(
            'SELECT t.*, u.name AS creator_name
               FROM employees_timeline t
               LEFT JOIN users u ON u.id = t.created_by
              WHERE t.employee_id = :id
              ORDER BY t.event_date DESC, t.id DESC',
            ['id' => $employeeId]
        );
    }

    public static function add(int $employeeId, string $eventType, string $description, string $eventDate, ?int $createdBy): int
    {
        return Database::insert('employees_timeline', [
            'employee_id' => $employeeId,
            'event_type' => $eventType,
            'description' => $description,
            'event_date' => $eventDate,
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM employees_timeline WHERE id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('employees_timeline', 'id = :id', ['id' => $id]);
    }
}

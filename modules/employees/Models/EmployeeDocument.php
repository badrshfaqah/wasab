<?php

namespace Modules\Employees\Models;

use App\Core\Database;

class EmployeeDocument
{
    public static function forEmployee(int $employeeId): array
    {
        return Database::select(
            'SELECT * FROM employees_documents WHERE employee_id = :id ORDER BY id DESC',
            ['id' => $employeeId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM employees_documents WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('employees_documents', $data);
    }

    public static function delete(int $id): void
    {
        Database::delete('employees_documents', 'id = :id', ['id' => $id]);
    }
}

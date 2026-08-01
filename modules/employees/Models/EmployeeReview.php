<?php

namespace Modules\Employees\Models;

use App\Core\Database;

/** تقييم أداء دوري لموظف. */
class EmployeeReview
{
    public const RATINGS = [1, 2, 3, 4, 5];

    public static function ratingLabels(): array
    {
        return [
            1 => 'ضعيف',
            2 => 'دون المتوقع',
            3 => 'يلبّي التوقعات',
            4 => 'يفوق التوقعات',
            5 => 'متميّز',
        ];
    }

    public static function forEmployee(int $employeeId): array
    {
        return Database::select(
            'SELECT r.*, u.name AS reviewer_name
               FROM employees_reviews r
               LEFT JOIN users u ON u.id = r.reviewer_id
              WHERE r.employee_id = :id
              ORDER BY COALESCE(r.review_date, r.created_at) DESC, r.id DESC',
            ['id' => $employeeId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM employees_reviews WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('employees_reviews', $data);
    }

    public static function delete(int $id): void
    {
        Database::delete('employees_reviews', 'id = :id', ['id' => $id]);
    }

    /** متوسط آخر التقييمات لموظف (أو null إن لا يوجد) - لعرض مختصر. */
    public static function averageRating(int $employeeId): ?float
    {
        $row = Database::first(
            'SELECT AVG(overall_rating) AS avg_rating FROM employees_reviews WHERE employee_id = :id',
            ['id' => $employeeId]
        );
        return $row && $row['avg_rating'] !== null ? round((float) $row['avg_rating'], 1) : null;
    }
}

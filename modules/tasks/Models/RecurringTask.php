<?php

namespace Modules\Tasks\Models;

use App\Core\Database;

/** قالب مهمة متكررة: يولّد مهمة فعلية كل فترة عبر cron. */
class RecurringTask
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public static function frequencyLabels(): array
    {
        return ['daily' => 'يومي', 'weekly' => 'أسبوعي', 'monthly' => 'شهري'];
    }

    public static function forCompany(int $companyId): array
    {
        return Database::select(
            'SELECT r.*, u.name AS assignee_name
               FROM tasks_recurring r
               LEFT JOIN users u ON u.id = r.assignee_id
              WHERE r.company_id = :c
              ORDER BY r.is_active DESC, r.next_run ASC',
            ['c' => $companyId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM tasks_recurring WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('tasks_recurring', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('tasks_recurring', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('tasks_recurring', 'id = :id', ['id' => $id]);
    }

    /** القوالب المستحقة التوليد (نشطة و next_run اليوم أو أقدم). */
    public static function due(): array
    {
        return Database::select(
            'SELECT * FROM tasks_recurring WHERE is_active = 1 AND next_run <= CURDATE()'
        );
    }

    /** يحسب تاريخ التوليد التالي بناءً على التكرار (يبني على التاريخ المُعطى). */
    public static function advance(string $fromDate, string $frequency): string
    {
        $ts = strtotime($fromDate);
        return match ($frequency) {
            'daily' => date('Y-m-d', strtotime('+1 day', $ts)),
            'monthly' => date('Y-m-d', strtotime('+1 month', $ts)),
            default => date('Y-m-d', strtotime('+7 days', $ts)),
        };
    }
}

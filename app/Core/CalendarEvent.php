<?php

namespace App\Core;

/**
 * أحداث تقويم خاصة بالشركة (إجازات، مناسبات، أحداث داخلية...) يضيفها مدير الشركة أو
 * مدير النظام يدوياً من صفحة التقويم - بخلاف أحداث الإضافات المُشتقة تلقائياً من
 * بياناتها (استحقاق مهمة، انتهاء ملف...)، هذه الأحداث مستقلة تماماً عن أي إضافة.
 */
class CalendarEvent
{
    /**
     * أحداث الشركة العامة (user_id NULL) + الأحداث الشخصية للمستخدم الحالي فقط -
     * الأحداث الشخصية لبقية الموظفين لا تظهر لغير أصحابها.
     */
    public static function forRange(int $companyId, string $fromDate, string $toDate, ?int $userId = null): array
    {
        return Database::select(
            'SELECT e.*, u.name AS creator_name
               FROM calendar_events e
               LEFT JOIN users u ON u.id = e.created_by
              WHERE e.company_id = :c AND e.event_date BETWEEN :from AND :to
                AND (e.user_id IS NULL OR e.user_id = :uid)
              ORDER BY e.event_date',
            ['c' => $companyId, 'from' => $fromDate, 'to' => $toDate, 'uid' => $userId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM calendar_events WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('calendar_events', $data);
    }

    public static function delete(int $id): void
    {
        Database::delete('calendar_events', 'id = :id', ['id' => $id]);
    }
}

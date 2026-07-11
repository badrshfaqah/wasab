<?php

namespace Modules\Meetings\Models;

use App\Core\Database;

class Meeting
{
    public static function statusLabels(): array
    {
        return [
            'scheduled' => 'مجدول',
            'completed' => 'منتهٍ',
            'cancelled' => 'ملغى',
        ];
    }

    public static function typeLabels(): array
    {
        return ['in_person' => 'حضوري', 'online' => 'اون لاين'];
    }

    public static function recurrenceLabels(): array
    {
        return ['none' => 'لا يتكرر', 'weekly' => 'أسبوعياً', 'monthly' => 'شهرياً'];
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM meetings_meetings WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('meetings_meetings', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('meetings_meetings', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('meetings_meetings', 'id = :id', ['id' => $id]);
    }

    /** الاجتماعات التي يظهر له فيها اهتمام مباشر: أنشأها، أو مدعو لها (لا يشمل ما بعد الحذف). */
    private static function visibilityJoin(): string
    {
        return 'LEFT JOIN meetings_attendees ma ON ma.meeting_id = m.id AND ma.user_id = :vis_uid';
    }

    public static function paginate(int $companyId, int $userId, bool $seeAll, array $filters, int $page, int $perPage): array
    {
        [$where, $params] = self::buildFilters($companyId, $userId, $seeAll, $filters);
        $join = self::visibilityJoin();
        $params['vis_uid'] = $userId;

        $total = Database::first(
            "SELECT COUNT(DISTINCT m.id) AS c FROM meetings_meetings m {$join} WHERE {$where}",
            $params
        )['c'] ?? 0;

        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT DISTINCT m.*, u.name AS creator_name
               FROM meetings_meetings m
               {$join}
               LEFT JOIN users u ON u.id = m.created_by
              WHERE {$where}
              ORDER BY m.starts_at DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => (int) $total];
    }

    private static function buildFilters(int $companyId, int $userId, bool $seeAll, array $filters): array
    {
        $where = 'm.company_id = :company_id';
        $params = ['company_id' => $companyId];

        if (!$seeAll) {
            $where .= ' AND (m.created_by = :creator_id OR ma.user_id = :vis_uid2)';
            $params['creator_id'] = $userId;
            $params['vis_uid2'] = $userId;
        }
        if (!empty($filters['status'])) {
            $where .= ' AND m.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND m.title LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        return [$where, $params];
    }

    public static function upcomingForUser(int $companyId, int $userId, int $limit = 5): array
    {
        return Database::select(
            "SELECT DISTINCT m.*
               FROM meetings_meetings m
               LEFT JOIN meetings_attendees ma ON ma.meeting_id = m.id AND ma.user_id = :u1
              WHERE m.company_id = :c AND m.status = 'scheduled' AND m.starts_at >= NOW()
                AND (m.created_by = :u2 OR ma.user_id = :u3)
              ORDER BY m.starts_at ASC
              LIMIT {$limit}",
            ['c' => $companyId, 'u1' => $userId, 'u2' => $userId, 'u3' => $userId]
        );
    }

    public static function pendingResponseForUser(int $companyId, int $userId, int $limit = 5): array
    {
        return Database::select(
            "SELECT DISTINCT m.*
               FROM meetings_meetings m
               JOIN meetings_attendees ma ON ma.meeting_id = m.id
              WHERE m.company_id = :c AND m.status = 'scheduled' AND m.starts_at >= NOW()
                AND ma.user_id = :u AND ma.response = 'pending'
              ORDER BY m.starts_at ASC
              LIMIT {$limit}",
            ['c' => $companyId, 'u' => $userId]
        );
    }

    public static function countPendingResponse(int $companyId, int $userId): int
    {
        return Database::first(
            "SELECT COUNT(DISTINCT m.id) AS c
               FROM meetings_meetings m
               JOIN meetings_attendees ma ON ma.meeting_id = m.id
              WHERE m.company_id = :c AND m.status = 'scheduled' AND m.starts_at >= NOW()
                AND ma.user_id = :u AND ma.response = 'pending'",
            ['c' => $companyId, 'u' => $userId]
        )['c'] ?? 0;
    }

    /** لمزوّد التقويم: كل الاجتماعات (المُنشأة أو المدعو لها) ضمن نطاق تاريخ معيّن. */
    public static function forCalendarRange(int $companyId, int $userId, bool $seeAll, string $fromDate, string $toDate): array
    {
        $where = 'm.company_id = :c AND m.status != "cancelled" AND DATE(m.starts_at) BETWEEN :from AND :to';
        $params = ['c' => $companyId, 'from' => $fromDate, 'to' => $toDate, 'u1' => $userId];
        if (!$seeAll) {
            $where .= ' AND (m.created_by = :u2 OR ma.user_id = :u3)';
            $params['u2'] = $userId;
            $params['u3'] = $userId;
        }

        return Database::select(
            "SELECT DISTINCT m.id, m.title, m.starts_at
               FROM meetings_meetings m
               LEFT JOIN meetings_attendees ma ON ma.meeting_id = m.id AND ma.user_id = :u1
              WHERE {$where}
              ORDER BY m.starts_at",
            $params
        );
    }

    /**
     * يبحث عن اجتماعات أخرى مجدولة (لنفس الشركة) تتعارض زمنياً مع الحاضرين الداخليين
     * المطلوبين (سواء كحاضرين أو كمنشئين لاجتماعاتهم الخاصة) - ينبّه فقط، لا يمنع الحفظ.
     * الاجتماع بلا وقت نهاية يُحتسب بساعة واحدة افتراضياً عند فحص التداخل.
     */
    public static function findConflicts(int $companyId, array $attendeeUserIds, int $creatorId, string $startsAt, ?string $endsAt, ?int $excludeMeetingId): array
    {
        $newEnd = $endsAt ?: date('Y-m-d H:i:s', strtotime($startsAt) + 3600);
        $excludeId = $excludeMeetingId ?? 0;
        $attendeeUserIds = array_values(array_unique(array_map('intval', $attendeeUserIds)));

        $queries = [];
        $params = [];

        if ($attendeeUserIds) {
            $placeholders = [];
            foreach ($attendeeUserIds as $i => $uid) {
                $key = "auid{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $uid;
            }
            $params['ac'] = $companyId;
            $params['aex'] = $excludeId;
            $params['aend'] = $newEnd;
            $params['astart'] = $startsAt;
            $queries[] = '(SELECT m.id, m.title, m.starts_at, u.name AS person_name
                FROM meetings_meetings m
                JOIN meetings_attendees a ON a.meeting_id = m.id
                JOIN users u ON u.id = a.user_id
               WHERE m.company_id = :ac AND m.status = "scheduled" AND m.id != :aex
                 AND a.user_id IN (' . implode(',', $placeholders) . ')
                 AND m.starts_at < :aend
                 AND COALESCE(m.ends_at, DATE_ADD(m.starts_at, INTERVAL 60 MINUTE)) > :astart)';
        }

        $params['cc'] = $companyId;
        $params['cex'] = $excludeId;
        $params['cend'] = $newEnd;
        $params['cstart'] = $startsAt;
        $params['ccreator'] = $creatorId;
        $queries[] = '(SELECT m.id, m.title, m.starts_at, u.name AS person_name
            FROM meetings_meetings m
            JOIN users u ON u.id = m.created_by
           WHERE m.company_id = :cc AND m.status = "scheduled" AND m.id != :cex
             AND m.created_by = :ccreator
             AND m.starts_at < :cend
             AND COALESCE(m.ends_at, DATE_ADD(m.starts_at, INTERVAL 60 MINUTE)) > :cstart)';

        return Database::select(implode(' UNION ', $queries) . ' ORDER BY starts_at', $params);
    }

    /**
     * يولّد تواريخ المناسبات القادمة لسلسلة متكررة (أسبوعياً/شهرياً) بدءاً من أول موعد
     * وحتى تاريخ الانتهاء المحدَّد، بحد أقصى $maxOccurrences احترازاً من سلسلة طويلة جداً
     * بالخطأ. يعيد أزواج [starts_at, ends_at] بنفس فرق الوقت الأصلي بين البداية والنهاية.
     */
    public static function generateOccurrences(string $firstStartsAt, ?string $firstEndsAt, string $rule, string $endDate, int $maxOccurrences = 52): array
    {
        $durationSeconds = $firstEndsAt ? (strtotime($firstEndsAt) - strtotime($firstStartsAt)) : null;
        $intervalSpec = $rule === 'monthly' ? '+1 month' : '+1 week';
        $endTs = strtotime($endDate . ' 23:59:59');

        $occurrences = [];
        $currentStart = strtotime($firstStartsAt);
        $count = 0;

        while ($count < $maxOccurrences) {
            $currentStart = strtotime($intervalSpec, $currentStart);
            if ($currentStart > $endTs) {
                break;
            }
            $startsAt = date('Y-m-d H:i:s', $currentStart);
            $endsAt = $durationSeconds !== null ? date('Y-m-d H:i:s', $currentStart + $durationSeconds) : null;
            $occurrences[] = [$startsAt, $endsAt];
            $count++;
        }

        return $occurrences;
    }

    /** يحذف كل المواعيد القادمة (غير هذا الموعد) بنفس سلسلة التكرار - أي "إيقاف التكرار من هنا فصاعداً". */
    public static function stopRecurrence(int $groupId, int $companyId, int $keepMeetingId): int
    {
        return Database::delete(
            'meetings_meetings',
            'recurrence_group_id = :g AND company_id = :c AND starts_at > NOW() AND id != :keep',
            ['g' => $groupId, 'c' => $companyId, 'keep' => $keepMeetingId]
        );
    }
}

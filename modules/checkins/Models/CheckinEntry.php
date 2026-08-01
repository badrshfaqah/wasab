<?php

namespace Modules\Checkins\Models;

use App\Core\Database;

class CheckinEntry
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM checkins_entries WHERE id = :id', ['id' => $id]);
    }

    public static function forUserOnDate(int $userId, string $date): ?array
    {
        return Database::first(
            'SELECT * FROM checkins_entries WHERE user_id = :u AND entry_date = :d',
            ['u' => $userId, 'd' => $date]
        );
    }

    /** مقياس المعنويات: 1 (مرهق) .. 5 (ممتاز) مع رمز ووصف. */
    public static function moodScale(): array
    {
        return [
            1 => ['emoji' => '😣', 'label' => 'مرهق'],
            2 => ['emoji' => '🙁', 'label' => 'غير جيد'],
            3 => ['emoji' => '😐', 'label' => 'عادي'],
            4 => ['emoji' => '🙂', 'label' => 'جيد'],
            5 => ['emoji' => '😄', 'label' => 'ممتاز'],
        ];
    }

    /** حفظ متابعة اليوم (إنشاء أو تحديث) - يعيد معرّف السجل. */
    public static function upsert(int $companyId, int $userId, string $date, ?string $done, ?string $plan, ?string $blockers, ?int $mood = null): int
    {
        $existing = self::forUserOnDate($userId, $date);
        if ($existing) {
            Database::update('checkins_entries', [
                'mood' => $mood,
                'done_text' => $done,
                'plan_text' => $plan,
                'blockers_text' => $blockers,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $existing['id']]);
            return (int) $existing['id'];
        }

        return Database::insert('checkins_entries', [
            'company_id' => $companyId,
            'user_id' => $userId,
            'entry_date' => $date,
            'mood' => $mood,
            'done_text' => $done,
            'plan_text' => $plan,
            'blockers_text' => $blockers,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** استبدال مهام السجل لنوع معين (منجزة/مخطط لها). */
    public static function setTasks(int $entryId, string $kind, array $taskIds): void
    {
        Database::delete('checkins_entry_tasks', 'entry_id = :e AND kind = :k', ['e' => $entryId, 'k' => $kind]);
        foreach (array_unique(array_map('intval', $taskIds)) as $taskId) {
            if ($taskId > 0) {
                Database::insert('checkins_entry_tasks', ['entry_id' => $entryId, 'task_id' => $taskId, 'kind' => $kind]);
            }
        }
    }

    /** مهام سجل معين بعناوينها (تُستدعى فقط عندما تكون إضافة المهام مفعّلة). */
    public static function tasksForEntries(array $entryIds): array
    {
        if (!$entryIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        $rows = Database::select(
            "SELECT et.entry_id, et.kind, et.task_id, t.title, t.status
               FROM checkins_entry_tasks et
               LEFT JOIN tasks_tasks t ON t.id = et.task_id
              WHERE et.entry_id IN ({$placeholders})",
            array_values(array_map('intval', $entryIds))
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['entry_id']][$row['kind']][] = $row;
        }
        return $grouped;
    }

    public static function historyForUser(int $userId, int $limit = 14): array
    {
        $limit = max(1, min(60, $limit));
        return Database::select(
            "SELECT * FROM checkins_entries WHERE user_id = :u ORDER BY entry_date DESC LIMIT {$limit}",
            ['u' => $userId]
        );
    }

    /**
     * لوحة الفريق ليوم معين: كل مستخدمي الشركة النشطين (موظفون ومدراء) مع
     * سجل متابعتهم إن وُجد - من لم يسجل يظهر صفه فارغاً ليعرف المدير من تأخر.
     */
    public static function teamForDate(int $companyId, string $date): array
    {
        return Database::select(
            "SELECT u.id AS user_id, u.name AS user_name,
                    e.id AS entry_id, e.mood, e.done_text, e.plan_text, e.blockers_text, e.blocker_task_id,
                    e.created_at AS submitted_at, e.updated_at
               FROM users u
               LEFT JOIN checkins_entries e ON e.user_id = u.id AND e.entry_date = :d
              WHERE u.company_id = :c AND u.status = 'active'
              ORDER BY (e.id IS NULL) DESC, u.name",
            ['c' => $companyId, 'd' => $date]
        );
    }

    /**
     * تقرير أسبوعي مجمّع للفريق: لكل موظف نشط عدد أيام التسجيل، عدد المعوقات،
     * ومتوسط المعنويات خلال المدى [from..to]. من لم يسجّل يظهر بأصفار.
     */
    public static function weeklyReport(int $companyId, string $from, string $to): array
    {
        return Database::select(
            "SELECT u.id AS user_id, u.name AS user_name,
                    COUNT(e.id) AS entries_count,
                    SUM(e.blockers_text IS NOT NULL AND e.blockers_text <> '') AS blockers_count,
                    AVG(e.mood) AS avg_mood
               FROM users u
               LEFT JOIN checkins_entries e
                      ON e.user_id = u.id AND e.entry_date BETWEEN :from AND :to
              WHERE u.company_id = :c AND u.status = 'active'
              GROUP BY u.id, u.name
              ORDER BY entries_count DESC, u.name",
            ['c' => $companyId, 'from' => $from, 'to' => $to]
        );
    }

    /**
     * سجلات المدى مفهرسة [user_id][entry_date] => صف مختصر (للشبكة اليومية بالتقرير).
     */
    public static function entriesInRange(int $companyId, string $from, string $to): array
    {
        $rows = Database::select(
            "SELECT user_id, entry_date, mood,
                    (blockers_text IS NOT NULL AND blockers_text <> '') AS has_blocker
               FROM checkins_entries
              WHERE company_id = :c AND entry_date BETWEEN :from AND :to",
            ['c' => $companyId, 'from' => $from, 'to' => $to]
        );
        $grid = [];
        foreach ($rows as $r) {
            $grid[(int) $r['user_id']][$r['entry_date']] = $r;
        }
        return $grid;
    }

    /** نسبة الالتزام بآخر أيام عمل: عدد السجلات مقابل (المستخدمون النشطون × أيام العمل). */
    public static function complianceLast7Days(int $companyId, array $workdays): array
    {
        $activeUsers = Database::count('users', 'company_id = :c AND status = "active"', ['c' => $companyId]);

        $workdayCount = 0;
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $ts = strtotime("-{$i} days");
            if (in_array((int) date('w', $ts), $workdays, true)) {
                $workdayCount++;
                $dates[] = date('Y-m-d', $ts);
            }
        }
        if (!$dates || $activeUsers < 1) {
            return ['rate' => 0, 'entries' => 0, 'expected' => 0];
        }

        $placeholders = implode(',', array_fill(0, count($dates), '?'));
        $entries = (int) (Database::first(
            "SELECT COUNT(*) AS c FROM checkins_entries WHERE company_id = ? AND entry_date IN ({$placeholders})",
            array_merge([$companyId], $dates)
        )['c'] ?? 0);

        $expected = $activeUsers * $workdayCount;
        return [
            'rate' => $expected > 0 ? (int) round($entries * 100 / $expected) : 0,
            'entries' => $entries,
            'expected' => $expected,
        ];
    }

    public static function submittedTodayCount(int $companyId, string $date): int
    {
        return Database::count('checkins_entries', 'company_id = :c AND entry_date = :d', ['c' => $companyId, 'd' => $date]);
    }
}

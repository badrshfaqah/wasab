<?php

namespace Modules\Mobileapi\Controllers;

use App\Controllers\ApprovalsController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Notification;
use App\Core\Permission;
use App\Core\Setting;
use Modules\Mobileapi\Support\Api;

/**
 * شاشة "يومي" + البحث الموحد + التقويم الموحد لتطبيق الجوال.
 * كل قسم معزول بـ try/catch فلا يُسقط خطأُ وحدةٍ الرد كاملاً (نفس فلسفة مركز الموافقات).
 */
class TodayApiController
{
    /** GET /api/v1/today */
    public function index(): void
    {
        $user = Api::user();
        $userId = (int) $user['id'];
        $companyId = Auth::companyId();
        $today = date('Y-m-d');

        $safe = function (callable $fn, $fallback = null) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                log_exception($e);
                return $fallback;
            }
        };

        // ---------- الحضور والمتابعة ----------
        $attendance = null;
        $checkin = null;
        if ($companyId && ModuleManager::isActive('checkins')) {
            $attendance = $safe(fn () => Database::first(
                'SELECT id, work_date, in_at, out_at FROM checkins_attendance
                  WHERE company_id = :c AND user_id = :u AND work_date = :d LIMIT 1',
                ['c' => $companyId, 'u' => $userId, 'd' => $today]
            ));

            $canSubmit = Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('checkins.submit');
            $entry = $safe(fn () => Database::first(
                'SELECT id FROM checkins_entries WHERE user_id = :u AND entry_date = :d LIMIT 1',
                ['u' => $userId, 'd' => $today]
            ));
            $workdays = $safe(fn () => \Modules\Checkins\Controllers\CheckinController::workdays($companyId), [0, 1, 2, 3, 4]);
            $checkin = [
                'can_submit' => $canSubmit,
                'submitted_today' => $entry !== null,
                'reminder_time' => $safe(fn () => (string) Setting::get('checkins_reminder_time', $companyId, '08:30'), '08:30'),
                'workdays' => array_values($workdays ?? [0, 1, 2, 3, 4]),
            ];
        }

        // ---------- اجتماعات اليوم والأسبوع (لجدولة التذكيرات المحلية) ----------
        $meetingsToday = [];
        $meetingsWeek = [];
        if ($companyId && ModuleManager::isActive('meetings') && (Permission::check('meetings.view') || Permission::check('meetings.manage'))) {
            $rows = $safe(fn () => Database::select(
                'SELECT DISTINCT m.id, m.title, m.type, m.location, m.starts_at, m.ends_at
                   FROM meetings_meetings m
                   LEFT JOIN meetings_attendees a ON a.meeting_id = m.id AND a.user_id = :u
                  WHERE m.company_id = :c AND m.status = "scheduled"
                    AND DATE(m.starts_at) BETWEEN :from AND :to
                    AND (m.created_by = :u2 OR a.user_id = :u3)
                  ORDER BY m.starts_at',
                ['c' => $companyId, 'u' => $userId, 'u2' => $userId, 'u3' => $userId, 'from' => $today, 'to' => date('Y-m-d', strtotime('+7 days'))]
            ), []);
            foreach ($rows ?? [] as $m) {
                $payload = [
                    'id' => (int) $m['id'],
                    'title' => $m['title'],
                    'type' => $m['type'],
                    'location' => $m['location'] ?? null,
                    'starts_at' => $m['starts_at'],
                    'ends_at' => $m['ends_at'] ?? null,
                ];
                if (substr((string) $m['starts_at'], 0, 10) === $today) {
                    $meetingsToday[] = $payload;
                }
                $meetingsWeek[] = $payload;
            }
        }

        // ---------- مهام اليوم والمتأخرة ----------
        $tasksDue = [];
        $overdueCount = 0;
        if ($companyId && ModuleManager::isActive('tasks') && Permission::check('tasks.view')) {
            $tasksDue = $safe(fn () => array_map(fn ($t) => [
                'id' => (int) $t['id'],
                'title' => $t['title'],
                'status' => $t['status'],
                'priority' => $t['priority'],
                'due_date' => $t['due_date'],
            ], Database::select(
                'SELECT id, title, status, priority, due_date FROM tasks_tasks
                  WHERE company_id = :c AND assignee_id = :u
                    AND status NOT IN ("done","cancelled")
                    AND due_date IS NOT NULL AND due_date <= :d
                  ORDER BY due_date, FIELD(priority, "urgent","high","medium","low") LIMIT 10',
                ['c' => $companyId, 'u' => $userId, 'd' => $today]
            )), []);
            $overdueCount = $safe(fn () => (int) (Database::first(
                'SELECT COUNT(*) AS c FROM tasks_tasks
                  WHERE company_id = :c AND assignee_id = :u
                    AND status NOT IN ("done","cancelled")
                    AND due_date IS NOT NULL AND due_date < :d',
                ['c' => $companyId, 'u' => $userId, 'd' => $today]
            )['c'] ?? 0), 0);
        }

        // ---------- بانتظار قراري ----------
        $approvals = ['total' => 0, 'tasks' => [], 'invites' => []];
        if ($companyId) {
            $approvals['total'] = $safe(fn () => ApprovalsController::pendingCount(), 0) ?? 0;
            if (ModuleManager::isActive('tasks')) {
                $approvals['tasks'] = $safe(fn () => array_map(fn ($t) => [
                    'id' => (int) $t['id'],
                    'title' => $t['title'],
                ], Database::select(
                    'SELECT id, title FROM tasks_tasks
                      WHERE company_id = :c AND requires_approval = 1 AND approved_at IS NULL
                        AND approver_id = :u AND status = "in_review" ORDER BY id DESC LIMIT 10',
                    ['c' => $companyId, 'u' => $userId]
                )), []) ?? [];
            }
            if (ModuleManager::isActive('meetings')) {
                $approvals['invites'] = $safe(fn () => array_map(fn ($m) => [
                    'id' => (int) $m['id'],
                    'title' => $m['title'],
                    'starts_at' => $m['starts_at'],
                ], Database::select(
                    'SELECT m.id, m.title, m.starts_at
                       FROM meetings_attendees a JOIN meetings_meetings m ON m.id = a.meeting_id
                      WHERE m.company_id = :c AND a.user_id = :u AND a.response = "pending"
                        AND m.status = "scheduled" AND m.starts_at >= NOW()
                      ORDER BY m.starts_at LIMIT 10',
                    ['c' => $companyId, 'u' => $userId]
                )), []) ?? [];
            }
        }

        // ---------- عدادات سريعة ----------
        $unreadInbox = 0;
        if ($companyId && ModuleManager::isActive('inbox') && Permission::check('inbox.view')) {
            $unreadInbox = $safe(fn () => (int) (Database::first(
                'SELECT COUNT(*) AS c FROM inbox_messages WHERE company_id = :c AND is_read = 0',
                ['c' => $companyId]
            )['c'] ?? 0), 0);
        }

        Api::ok([
            'date' => $today,
            'attendance' => $attendance ? [
                'id' => (int) $attendance['id'],
                'work_date' => $attendance['work_date'],
                'in_at' => $attendance['in_at'],
                'out_at' => $attendance['out_at'] ?? null,
            ] : null,
            'checkin' => $checkin,
            'meetings_today' => $meetingsToday,
            'meetings_week' => $meetingsWeek,
            'tasks_due' => $tasksDue,
            'overdue_count' => $overdueCount,
            'approvals' => $approvals,
            'unread_notifications' => Notification::unreadCount($userId),
            'unread_inbox' => $unreadInbox,
        ]);
    }

    /** GET /api/v1/search?q= - البحث الموحد عبر كل الوحدات المفعلة. */
    public function search(): void
    {
        $user = Api::user();
        $q = trim((string) Api::input('q', ''));
        if (mb_strlen($q) < 2) {
            Api::ok(['results' => []]);
        }

        $results = [];
        try {
            foreach (ModuleManager::collectSearchResults($user, $q) as $item) {
                $results[] = [
                    'title' => $item['title'] ?? '',
                    'subtitle' => $item['subtitle'] ?? null,
                    'icon' => $item['icon'] ?? null,
                    'path' => $this->relativeUrl($item['url'] ?? null),
                    'module' => $item['module'] ?? '',
                    'module_name' => $item['module_name'] ?? '',
                ];
            }
        } catch (\Throwable $e) {
            log_exception($e);
        }

        Api::ok(['results' => $results]);
    }

    /** GET /api/v1/calendar?from=YYYY-MM-DD&to=YYYY-MM-DD - أحداث الوحدات + أحداث الشركة. */
    public function calendar(): void
    {
        $user = Api::user();

        $from = (string) Api::input('from', date('Y-m-01'));
        $to = (string) Api::input('to', date('Y-m-t'));
        foreach ([$from, $to] as $value) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || strtotime($value) === false) {
                Api::error('صيغة التاريخ غير صحيحة.', 422, 'validation');
            }
        }
        // حد أقصى 3 أشهر لكل طلب حتى لا تثقل الاستعلامات.
        if ((strtotime($to) - strtotime($from)) > 93 * 86400) {
            Api::error('النطاق الزمني كبير جداً (الحد 3 أشهر).', 422, 'validation');
        }

        $events = [];
        try {
            foreach (ModuleManager::collectCalendarEvents($user, $from, $to) as $event) {
                $events[] = [
                    'date' => $event['date'] ?? '',
                    'time' => $event['time'] ?? null,
                    'title' => $event['title'] ?? '',
                    'path' => $this->relativeUrl($event['url'] ?? null),
                    'module' => $event['module'] ?? '',
                ];
            }
        } catch (\Throwable $e) {
            log_exception($e);
        }

        $companyId = Auth::companyId();
        if ($companyId) {
            try {
                foreach (\App\Core\CalendarEvent::forRange($companyId, $from, $to, (int) $user['id']) as $event) {
                    $events[] = [
                        'date' => $event['event_date'],
                        'time' => null,
                        'title' => '📌 ' . $event['title'],
                        'path' => null,
                        'module' => 'core',
                    ];
                }
            } catch (\Throwable $e) {
                log_exception($e);
            }
        }

        usort($events, fn ($a, $b) => [$a['date'], $a['time'] ?? ''] <=> [$b['date'], $b['time'] ?? '']);

        Api::ok(['from' => $from, 'to' => $to, 'events' => $events]);
    }

    private function relativeUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: null;
        if ($path === null) {
            return null;
        }
        $query = parse_url($url, PHP_URL_QUERY);
        return $path . ($query ? '?' . $query : '');
    }
}

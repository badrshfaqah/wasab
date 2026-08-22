<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Notification;
use App\Core\Permission;
use Modules\Checkins\Controllers\CheckinController;
use Modules\Checkins\Models\CheckinEntry;
use Modules\Mobileapi\Support\Api;

/**
 * نقاط JSON لوحدة التحضير (المتابعة اليومية + الحضور والانصراف)
 * بنفس قواعد CheckinController الويب.
 */
class CheckinsApiController
{
    // ---------- الشاشة الرئيسية للوحدة ----------

    /** GET /api/v1/checkins - متابعة اليوم + السجل + حضور اليوم + مهامي للاختيار. */
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canSubmit() && !$this->canViewTeam()) {
            Api::error('لا تملك صلاحية استخدام التحضير.', 403, 'forbidden');
        }

        $userId = Auth::id();
        $today = date('Y-m-d');
        $entry = CheckinEntry::forUserOnDate($userId, $today);
        $tasksActive = ModuleManager::isActive('tasks');

        $myDoneTasks = [];
        $myOpenTasks = [];
        $selectedTasks = ['done' => [], 'planned' => []];
        if ($tasksActive) {
            $myDoneTasks = Database::select(
                'SELECT id, title FROM tasks_tasks
                  WHERE assignee_id = :u AND status = "done" AND updated_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
                  ORDER BY updated_at DESC LIMIT 15',
                ['u' => $userId]
            );
            $myOpenTasks = Database::select(
                'SELECT id, title FROM tasks_tasks
                  WHERE assignee_id = :u AND status IN ("todo","in_progress","in_review")
                  ORDER BY due_date IS NULL, due_date LIMIT 20',
                ['u' => $userId]
            );
            if ($entry) {
                $grouped = CheckinEntry::tasksForEntries([(int) $entry['id']]);
                foreach ($grouped[(int) $entry['id']] ?? [] as $kind => $rows) {
                    $selectedTasks[$kind] = array_map(fn ($r) => (int) $r['task_id'], $rows);
                }
            }
        }

        $attendance = Database::first(
            'SELECT * FROM checkins_attendance WHERE company_id = :c AND user_id = :u AND work_date = :d LIMIT 1',
            ['c' => $companyId, 'u' => $userId, 'd' => $today]
        );

        Api::ok([
            'today' => $today,
            'entry' => $entry ? $this->entryPayload($entry) : null,
            'selected_tasks' => $selectedTasks,
            'history' => array_map([$this, 'entryPayload'], CheckinEntry::historyForUser($userId, 14)),
            'attendance' => $attendance ? $this->attendancePayload($attendance) : null,
            'my_done_tasks' => array_map(fn ($t) => ['id' => (int) $t['id'], 'title' => $t['title']], $myDoneTasks),
            'my_open_tasks' => array_map(fn ($t) => ['id' => (int) $t['id'], 'title' => $t['title']], $myOpenTasks),
            'tasks_active' => $tasksActive,
            'can_submit' => $this->canSubmit(),
            'can_view_team' => $this->canViewTeam(),
        ]);
    }

    /** POST /api/v1/checkins - حفظ متابعة اليوم (إنشاء أو تحديث). */
    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canSubmit()) {
            Api::error('لا تملك صلاحية تسجيل المتابعة.', 403, 'forbidden');
        }

        $done = $this->cleanText(Api::input('done_text'));
        $plan = $this->cleanText(Api::input('plan_text'));
        $blockers = $this->cleanText(Api::input('blockers_text'));
        $mood = (int) Api::input('mood', 0);
        $mood = ($mood >= 1 && $mood <= 5) ? $mood : null;
        $doneTasks = $this->intArray(Api::input('done_tasks'));
        $plannedTasks = $this->intArray(Api::input('planned_tasks'));

        if ($done === null && $plan === null && $blockers === null && $mood === null && !$doneTasks && !$plannedTasks) {
            Api::error('اكتب شيئاً واحداً على الأقل في المتابعة.', 422, 'validation');
        }

        $userId = Auth::id();
        $today = date('Y-m-d');
        $previous = CheckinEntry::forUserOnDate($userId, $today);

        $entryId = CheckinEntry::upsert($companyId, $userId, $today, $done, $plan, $blockers, $mood);

        if (ModuleManager::isActive('tasks')) {
            CheckinEntry::setTasks($entryId, 'done', $this->filterOwnTaskIds($doneTasks, $companyId));
            CheckinEntry::setTasks($entryId, 'planned', $this->filterOwnTaskIds($plannedTasks, $companyId));
        }

        // إشعار المدراء عند أول تسجيل معوق في اليوم فقط (نفس منطق الويب).
        if ($blockers !== null && empty($previous['blockers_text'])) {
            $this->notifyManagers(
                $companyId,
                '🚧 معوق جديد لدى ' . (Auth::user()['name'] ?? ''),
                mb_substr($blockers, 0, 120),
                route('/checkins/team?date=' . $today)
            );
        }

        ActivityLog::log('checkins.submit', 'checkin', (string) $entryId, 'تسجيل متابعة يومية من الجوال');
        Api::ok(['id' => $entryId, 'message' => 'تم حفظ متابعتك لليوم.'], 201);
    }

    // ---------- الحضور والانصراف ----------

    /** POST /api/v1/checkins/attendance/in  {lat?, lng?} */
    public function attendanceIn(): void
    {
        $companyId = $this->requireCompanyContext();
        $userId = Auth::id();
        $today = date('Y-m-d');

        $existing = Database::first(
            'SELECT id FROM checkins_attendance WHERE company_id = :c AND user_id = :u AND work_date = :d LIMIT 1',
            ['c' => $companyId, 'u' => $userId, 'd' => $today]
        );
        if ($existing) {
            Api::error('سجّلت حضورك اليوم مسبقاً.', 422, 'already_checked_in');
        }

        [$lat, $lng] = $this->coords();
        Database::insert('checkins_attendance', [
            'company_id' => $companyId,
            'user_id' => $userId,
            'work_date' => $today,
            'in_at' => date('Y-m-d H:i:s'),
            'in_lat' => $lat,
            'in_lng' => $lng,
        ]);

        $this->todayAttendanceResponse($companyId, $userId, $today, 'تم تسجيل الحضور.');
    }

    /** POST /api/v1/checkins/attendance/out  {lat?, lng?} */
    public function attendanceOut(): void
    {
        $companyId = $this->requireCompanyContext();
        $userId = Auth::id();
        $today = date('Y-m-d');

        $row = Database::first(
            'SELECT * FROM checkins_attendance WHERE company_id = :c AND user_id = :u AND work_date = :d LIMIT 1',
            ['c' => $companyId, 'u' => $userId, 'd' => $today]
        );
        if (!$row) {
            Api::error('لم تسجّل حضوراً اليوم بعد.', 422, 'not_checked_in');
        }
        if (!empty($row['out_at'])) {
            Api::error('سجّلت انصرافك اليوم مسبقاً.', 422, 'already_checked_out');
        }

        [$lat, $lng] = $this->coords();
        Database::update('checkins_attendance', [
            'out_at' => date('Y-m-d H:i:s'),
            'out_lat' => $lat,
            'out_lng' => $lng,
        ], 'id = :id', ['id' => $row['id']]);

        $this->todayAttendanceResponse($companyId, $userId, $today, 'تم تسجيل الانصراف.');
    }

    /** GET /api/v1/checkins/attendance?month=YYYY-MM&user_id= - سجل شهر كامل. */
    public function attendance(): void
    {
        $companyId = $this->requireCompanyContext();

        $month = (string) Api::input('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $userId = Auth::id();
        $requested = (int) Api::input('user_id', 0);
        if ($requested > 0 && $requested !== $userId && $this->canViewTeam()) {
            $userId = $requested;
        }

        $rows = Database::select(
            'SELECT * FROM checkins_attendance
              WHERE company_id = :c AND user_id = :u AND work_date BETWEEN :f AND :t
              ORDER BY work_date DESC',
            ['c' => $companyId, 'u' => $userId, 'f' => $from, 't' => $to]
        );

        Api::ok([
            'month' => $month,
            'user_id' => $userId,
            'rows' => array_map([$this, 'attendancePayload'], $rows),
        ]);
    }

    // ---------- الفريق ----------

    /** GET /api/v1/checkins/team?date=YYYY-MM-DD */
    public function team(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canViewTeam()) {
            Api::error('لا تملك صلاحية عرض متابعات الفريق.', 403, 'forbidden');
        }

        $date = (string) Api::input('date', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            $date = date('Y-m-d');
        }

        $rows = CheckinEntry::teamForDate($companyId, $date);

        Api::ok([
            'date' => $date,
            'rows' => array_map(fn ($r) => [
                'user_id' => (int) $r['user_id'],
                'user_name' => $r['user_name'],
                'entry_id' => isset($r['entry_id']) && $r['entry_id'] ? (int) $r['entry_id'] : null,
                'mood' => isset($r['mood']) && $r['mood'] !== null ? (int) $r['mood'] : null,
                'done_text' => $r['done_text'] ?? null,
                'plan_text' => $r['plan_text'] ?? null,
                'blockers_text' => $r['blockers_text'] ?? null,
                'blocker_task_id' => isset($r['blocker_task_id']) && $r['blocker_task_id'] ? (int) $r['blocker_task_id'] : null,
                'submitted_at' => $r['submitted_at'] ?? null,
            ], $rows),
        ]);
    }

    /** POST /api/v1/checkins/blocker-to-task  {entry_id, assignee_id} */
    public function blockerToTask(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canViewTeam()) {
            Api::error('لا تملك صلاحية تحويل المعوقات لمهام.', 403, 'forbidden');
        }
        if (!ModuleManager::isActive('tasks')) {
            Api::error('وحدة المهام غير مفعلة.', 422, 'module_inactive');
        }

        $entry = CheckinEntry::find((int) Api::input('entry_id', 0));
        if (!$entry || (int) $entry['company_id'] !== $companyId || empty($entry['blockers_text'])) {
            Api::error('المتابعة غير موجودة أو بلا معوق.', 404, 'not_found');
        }
        if (!empty($entry['blocker_task_id'])) {
            Api::error('سبق تحويل هذا المعوق إلى مهمة.', 422, 'already_converted');
        }

        $assigneeId = (int) Api::input('assignee_id', 0);
        $assignee = Database::first(
            'SELECT id, name FROM users WHERE id = :id AND company_id = :c AND status = "active" LIMIT 1',
            ['id' => $assigneeId, 'c' => $companyId]
        );
        if (!$assignee) {
            Api::error('اختر مسنداً إليه من مستخدمي شركتك.', 422, 'validation');
        }

        $owner = Database::first('SELECT id, name FROM users WHERE id = :id LIMIT 1', ['id' => $entry['user_id']]);
        $blocker = (string) $entry['blockers_text'];

        $taskId = Database::insert('tasks_tasks', [
            'company_id' => $companyId,
            'title' => mb_substr('حل معوق: ' . mb_substr($blocker, 0, 80), 0, 200),
            'description' => "معوق من المتابعة اليومية ({$entry['entry_date']}) لدى " . ($owner['name'] ?? '') . ":\n\n" . $blocker,
            'assignee_id' => $assigneeId,
            'creator_id' => Auth::id(),
            'priority' => 'high',
            'status' => 'todo',
            'due_date' => date('Y-m-d', strtotime('+1 day')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Database::update('checkins_entries', [
            'blocker_task_id' => $taskId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $entry['id']]);

        Notification::send($assigneeId, '📋 مهمة جديدة: حل معوق', mb_substr($blocker, 0, 120), route('/tasks/' . $taskId));
        if ((int) $entry['user_id'] !== $assigneeId) {
            Notification::send((int) $entry['user_id'], '✅ معوقك تحت المعالجة', '', route('/tasks/' . $taskId));
        }
        ActivityLog::log('checkins.blocker_to_task', 'task', (string) $taskId, 'تحويل معوق إلى مهمة من الجوال');

        Api::ok(['task_id' => $taskId], 201);
    }

    // ---------- مساعدات ----------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            Api::error('حسابك غير مرتبط بشركة.', 422, 'no_company');
        }
        return $companyId;
    }

    private function canSubmit(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('checkins.submit');
    }

    private function canViewTeam(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('checkins.view_team');
    }

    private function entryPayload(array $e): array
    {
        return [
            'id' => (int) $e['id'],
            'entry_date' => $e['entry_date'],
            'mood' => isset($e['mood']) && $e['mood'] !== null ? (int) $e['mood'] : null,
            'done_text' => $e['done_text'] ?? null,
            'plan_text' => $e['plan_text'] ?? null,
            'blockers_text' => $e['blockers_text'] ?? null,
            'blocker_task_id' => isset($e['blocker_task_id']) && $e['blocker_task_id'] ? (int) $e['blocker_task_id'] : null,
            'created_at' => $e['created_at'] ?? null,
            'updated_at' => $e['updated_at'] ?? null,
        ];
    }

    private function attendancePayload(array $a): array
    {
        return [
            'id' => (int) $a['id'],
            'work_date' => $a['work_date'],
            'in_at' => $a['in_at'],
            'out_at' => $a['out_at'] ?? null,
        ];
    }

    private function todayAttendanceResponse(int $companyId, int $userId, string $today, string $message): void
    {
        $row = Database::first(
            'SELECT * FROM checkins_attendance WHERE company_id = :c AND user_id = :u AND work_date = :d LIMIT 1',
            ['c' => $companyId, 'u' => $userId, 'd' => $today]
        );
        Api::ok([
            'message' => $message,
            'attendance' => $row ? $this->attendancePayload($row) : null,
        ]);
    }

    /** إحداثيات اختيارية: أرقام صحيحة النطاق وإلا null للاثنين (نفس تحقق الويب). */
    private function coords(): array
    {
        $lat = Api::input('lat');
        $lng = Api::input('lng');
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return [null, null];
        }
        return [round((float) $lat, 7), round((float) $lng, 7)];
    }

    private function cleanText($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        return mb_substr($text, 0, 5000);
    }

    private function intArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $value), fn ($v) => $v > 0)));
    }

    /** نفس حارس IDOR في الويب: فقط مهامي أنا داخل شركتي. */
    private function filterOwnTaskIds(array $ids, int $companyId): array
    {
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$companyId, Auth::id()]);
        $rows = Database::select(
            "SELECT id FROM tasks_tasks WHERE id IN ({$in}) AND company_id = ? AND assignee_id = ?",
            $params
        );
        return array_map(fn ($r) => (int) $r['id'], $rows);
    }

    private function notifyManagers(int $companyId, string $title, string $message, string $url): void
    {
        try {
            $rows = Database::select(
                'SELECT DISTINCT u.id
                   FROM users u
              LEFT JOIN user_roles ur ON ur.user_id = u.id
              LEFT JOIN role_permissions rp ON rp.role_id = ur.role_id
              LEFT JOIN permissions p ON p.id = rp.permission_id
                  WHERE u.company_id = :c AND u.status = "active" AND u.id != :me
                    AND (u.membership_type = "company_admin" OR p.permission_key = "checkins.view_team")',
                ['c' => $companyId, 'me' => Auth::id()]
            );
            foreach ($rows as $row) {
                Notification::send((int) $row['id'], $title, $message, $url);
            }
        } catch (\Throwable $e) {
            log_exception($e);
        }
    }
}

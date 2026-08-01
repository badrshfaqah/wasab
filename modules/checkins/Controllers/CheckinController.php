<?php

namespace Modules\Checkins\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Notification;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Setting;
use App\Core\View;
use Modules\Checkins\Models\CheckinEntry;

class CheckinController
{
    /** صفحتي: نموذج متابعة اليوم + سجلّي الأخير. */
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canSubmit() && !$this->canViewTeam()) {
            $this->forbidden();
            return;
        }

        $today = date('Y-m-d');
        $entry = CheckinEntry::forUserOnDate(Auth::id(), $today);

        $tasksActive = ModuleManager::isActive('tasks');
        $myDoneTasks = [];
        $myOpenTasks = [];
        if ($tasksActive) {
            // منجزة حديثاً (آخر يومين) ليؤشر عليها، ومفتوحة ليختار منها خطة اليوم
            $myDoneTasks = Database::select(
                "SELECT id, title FROM tasks_tasks
                  WHERE assignee_id = :u AND status = 'done'
                    AND updated_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
                  ORDER BY updated_at DESC LIMIT 15",
                ['u' => Auth::id()]
            );
            $myOpenTasks = Database::select(
                "SELECT id, title, status FROM tasks_tasks
                  WHERE assignee_id = :u AND status IN ('todo','in_progress','in_review')
                  ORDER BY due_date IS NULL, due_date LIMIT 20",
                ['u' => Auth::id()]
            );
        }

        $selected = ['done' => [], 'planned' => []];
        if ($entry) {
            foreach (CheckinEntry::tasksForEntries([$entry['id']])[(int) $entry['id']] ?? [] as $kind => $rows) {
                $selected[$kind] = array_column($rows, 'task_id');
            }
        }

        View::render('checkins::index', [
            'pageTitle' => 'المتابعة اليومية',
            'entry' => $entry,
            'today' => $today,
            'tasksActive' => $tasksActive,
            'myDoneTasks' => $myDoneTasks,
            'myOpenTasks' => $myOpenTasks,
            'selectedTasks' => $selected,
            'history' => CheckinEntry::historyForUser(Auth::id()),
            'canSubmit' => $this->canSubmit(),
            'canViewTeam' => $this->canViewTeam(),
            'canManage' => $this->canManage(),
        ]);
    }

    /** حفظ متابعة اليوم (إنشاء أو تعديل بنفس اليوم). */
    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canSubmit()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/checkins');

        $today = date('Y-m-d');
        $done = trim((string) Request::input('done_text', ''));
        $plan = trim((string) Request::input('plan_text', ''));
        $blockers = trim((string) Request::input('blockers_text', ''));
        $mood = (int) Request::input('mood', 0);
        $mood = ($mood >= 1 && $mood <= 5) ? $mood : null;

        $doneTasks = (array) ($_POST['done_tasks'] ?? []);
        $planTasks = (array) ($_POST['planned_tasks'] ?? []);

        if ($done === '' && $plan === '' && $blockers === '' && !$doneTasks && !$planTasks && !$mood) {
            flash_set('error', 'سجّل شيئاً واحداً على الأقل: إنجازاً أو خطة أو معوقاً أو معنوياتك.');
            redirect('/checkins');
        }

        $previous = CheckinEntry::forUserOnDate(Auth::id(), $today);
        $hadBlockers = $previous && trim((string) $previous['blockers_text']) !== '';

        $entryId = CheckinEntry::upsert(
            $companyId,
            Auth::id(),
            $today,
            mb_substr($done, 0, 5000) ?: null,
            mb_substr($plan, 0, 5000) ?: null,
            mb_substr($blockers, 0, 5000) ?: null,
            $mood
        );

        if (ModuleManager::isActive('tasks')) {
            // تصفية المعرّفات إلى مهام هذا المستخدم فعلاً داخل شركته - يمنع حقن
            // معرّفات مهام شركة أخرى (IDOR) أو نسب مهمة زميل لنفسه.
            $doneTasks = $this->filterOwnTaskIds($doneTasks, $companyId);
            $planTasks = $this->filterOwnTaskIds($planTasks, $companyId);
            CheckinEntry::setTasks($entryId, 'done', $doneTasks);
            CheckinEntry::setTasks($entryId, 'planned', $planTasks);
        }

        // معوق جديد => تنبيه فوري للمدراء (مرة واحدة عند أول تسجيل له، لا مع كل تعديل)
        if ($blockers !== '' && !$hadBlockers) {
            $this->notifyManagers(
                $companyId,
                '🚧 معوق جديد لدى ' . (Auth::user()['name'] ?? ''),
                mb_substr($blockers, 0, 120),
                route('/checkins/team?date=' . $today)
            );
        }

        ActivityLog::log('checkins.submit', 'checkin', $entryId, 'تسجيل المتابعة اليومية');
        flash_set('success', $previous ? 'تم تحديث متابعة اليوم.' : 'تم تسجيل متابعة اليوم - يوم موفق! 💪');
        redirect('/checkins');
    }

    /** لوحة الفريق الصباحية للمدير. */
    public function team(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canViewTeam()) {
            $this->forbidden();
            return;
        }

        $date = (string) Request::query('date', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            $date = date('Y-m-d');
        }

        $rows = CheckinEntry::teamForDate($companyId, $date);
        $entryIds = array_values(array_filter(array_column($rows, 'entry_id')));

        View::render('checkins::team', [
            'pageTitle' => 'متابعات الفريق',
            'date' => $date,
            'rows' => $rows,
            'entryTasks' => ModuleManager::isActive('tasks') ? CheckinEntry::tasksForEntries($entryIds) : [],
            'tasksActive' => ModuleManager::isActive('tasks'),
            'companyUsers' => Database::select(
                'SELECT id, name FROM users WHERE company_id = :c AND status = "active" ORDER BY name',
                ['c' => $companyId]
            ),
            'canManage' => $this->canManage(),
        ]);
    }

    /** التقرير الأسبوعي المجمّع للفريق (أيام التسجيل، المعوقات، متوسط المعنويات). */
    public function report(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canViewTeam()) {
            $this->forbidden();
            return;
        }

        // بداية الأسبوع = الأحد؛ يمكن التنقّل عبر ?from=YYYY-MM-DD (يُطبَّق على أحد ذلك الأسبوع)
        $fromParam = (string) Request::query('from', '');
        $base = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromParam) && strtotime($fromParam))
            ? strtotime($fromParam)
            : time();
        // أرجِع للأحد (w=0) لهذا الأسبوع
        $sunday = strtotime('-' . (int) date('w', $base) . ' days', $base);
        $from = date('Y-m-d', $sunday);
        $to = date('Y-m-d', strtotime('+6 days', $sunday));

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = date('Y-m-d', strtotime("+{$i} days", $sunday));
        }

        View::render('checkins::report', [
            'pageTitle' => 'التقرير الأسبوعي',
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'prevFrom' => date('Y-m-d', strtotime('-7 days', $sunday)),
            'nextFrom' => date('Y-m-d', strtotime('+7 days', $sunday)),
            'isCurrentWeek' => $to >= date('Y-m-d'),
            'rows' => CheckinEntry::weeklyReport($companyId, $from, $to),
            'grid' => CheckinEntry::entriesInRange($companyId, $from, $to),
            'moodScale' => CheckinEntry::moodScale(),
            'workdays' => self::workdays($companyId),
            'canManage' => $this->canManage(),
        ]);
    }

    /** تحويل معوق موظف إلى مهمة مسندة - قلب فكرة الإضافة. */
    public function blockerToTask(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canViewTeam()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/checkins/team');

        if (!ModuleManager::isActive('tasks')) {
            flash_set('error', 'إضافة المهام غير مفعّلة.');
            redirect('/checkins/team');
        }

        $entry = CheckinEntry::find((int) Request::input('entry_id', 0));
        $assigneeId = (int) Request::input('assignee_id', 0);
        if (!$entry || (int) $entry['company_id'] !== $companyId || trim((string) $entry['blockers_text']) === '') {
            flash_set('error', 'السجل غير موجود أو بلا معوق.');
            redirect('/checkins/team');
        }
        if ($entry['blocker_task_id']) {
            flash_set('error', 'هذا المعوق حُوّل لمهمة مسبقاً.');
            redirect('/checkins/team?date=' . $entry['entry_date']);
        }

        $assignee = Database::first(
            'SELECT id, name FROM users WHERE id = :id AND company_id = :c AND status = "active"',
            ['id' => $assigneeId, 'c' => $companyId]
        );
        if (!$assignee) {
            flash_set('error', 'اختر مسؤولاً صحيحاً للمهمة.');
            redirect('/checkins/team?date=' . $entry['entry_date']);
        }

        $owner = Database::first('SELECT name FROM users WHERE id = :id', ['id' => $entry['user_id']]);
        $blocker = trim((string) $entry['blockers_text']);

        $taskId = Database::insert('tasks_tasks', [
            'company_id' => $companyId,
            'title' => mb_substr('حل معوق: ' . mb_substr($blocker, 0, 80), 0, 200),
            'description' => "معوق سجّله {$owner['name']} بمتابعة يوم {$entry['entry_date']}:\n\n{$blocker}",
            'priority' => 'high',
            'status' => 'todo',
            'assignee_id' => (int) $assignee['id'],
            'creator_id' => Auth::id(),
            'due_date' => date('Y-m-d', strtotime('+1 day')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Database::update('checkins_entries', ['blocker_task_id' => $taskId, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $entry['id']]);

        Notification::send((int) $assignee['id'], '📋 مهمة جديدة: حل معوق', mb_substr($blocker, 0, 120), route('/tasks/' . $taskId));
        if ((int) $entry['user_id'] !== (int) $assignee['id']) {
            Notification::send((int) $entry['user_id'], '✅ معوقك تحت المعالجة', 'حُوّل معوقك لمهمة مسندة إلى ' . $assignee['name'], route('/tasks/' . $taskId));
        }

        ActivityLog::log('checkins.blocker_to_task', 'task', $taskId, "تحويل معوق ({$owner['name']}) لمهمة مسندة إلى {$assignee['name']}");
        flash_set('success', 'حُوّل المعوق لمهمة مسندة إلى ' . $assignee['name'] . '.');
        redirect('/checkins/team?date=' . $entry['entry_date']);
    }

    /** إعدادات الشركة: وقت التذكير وأيام العمل. */
    public function settings(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }

        View::render('checkins::settings', [
            'pageTitle' => 'إعدادات المتابعة اليومية',
            'reminderTime' => Setting::get('checkins_reminder_time', $companyId, '08:30'),
            'workdays' => self::workdays($companyId),
        ]);
    }

    public function saveSettings(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/checkins/settings');

        $time = trim((string) Request::input('reminder_time', '08:30'));
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $time = '08:30';
        }

        $days = array_values(array_intersect(array_map('intval', (array) ($_POST['workdays'] ?? [])), range(0, 6)));
        if (!$days) {
            $days = [0, 1, 2, 3, 4];
        }

        Setting::set('checkins_reminder_time', $time, $companyId);
        Setting::set('checkins_workdays', implode(',', $days), $companyId);

        ActivityLog::log('checkins.settings', 'setting', 'checkins', 'تحديث إعدادات المتابعة اليومية');
        flash_set('success', 'تم حفظ الإعدادات.');
        redirect('/checkins/settings');
    }

    // ---------------------------------------------------------------

    /** أيام العمل للشركة (بأرقام PHP: الأحد=0 ... السبت=6). */
    public static function workdays(int $companyId): array
    {
        $raw = (string) Setting::get('checkins_workdays', $companyId, '0,1,2,3,4');
        $days = array_values(array_intersect(array_map('intval', explode(',', $raw)), range(0, 6)));
        return $days ?: [0, 1, 2, 3, 4];
    }

    private function notifyManagers(int $companyId, string $title, string $message, string $url): void
    {
        try {
            $recipients = Database::select(
                'SELECT DISTINCT u.id
                   FROM users u
                   LEFT JOIN user_roles ur ON ur.user_id = u.id
                   LEFT JOIN role_permissions rp ON rp.role_id = ur.role_id
                   LEFT JOIN permissions p ON p.id = rp.permission_id
                  WHERE u.company_id = :c AND u.status = "active" AND u.id != :me
                    AND (u.membership_type = "company_admin" OR p.permission_key = "checkins.view_team")',
                ['c' => $companyId, 'me' => Auth::id()]
            );
            foreach ($recipients as $r) {
                Notification::send((int) $r['id'], $title, $message, $url);
            }
        } catch (\Throwable $e) {
            log_exception($e);
        }
    }

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('checkins::no-company', ['pageTitle' => 'المتابعة اليومية']);
            exit;
        }
        return $companyId;
    }

    private function canSubmit(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('checkins.submit');
    }

    /**
     * تصفية معرّفات المهام الواردة من الطلب إلى ما هو مسند فعلاً للمستخدم الحالي
     * داخل شركته - حماية من حقن معرّفات مهام لا يملكها (IDOR / نسب مهمة الغير).
     */
    private function filterOwnTaskIds(array $taskIds, int $companyId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $taskIds), fn ($id) => $id > 0)));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::select(
            "SELECT id FROM tasks_tasks
              WHERE id IN ({$placeholders}) AND company_id = ? AND assignee_id = ?",
            array_merge($ids, [$companyId, Auth::id()])
        );
        return array_column($rows, 'id');
    }

    private function canViewTeam(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('checkins.view_team');
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('checkins.manage');
    }

    private function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    private function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', [], '');
    }
}

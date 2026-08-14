<?php

namespace Modules\Employees\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Notification;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;
use Modules\Employees\Models\Employee;
use Modules\Employees\Models\EmployeeLeave;
use Modules\Employees\Models\EmployeeTimeline;

/**
 * الإجازات والأذونات: الموظف المربوط حسابه بملف وظيفي يقدّم طلباته ويتابعها،
 * ومن يملك صلاحية الإدارة يعتمد أو يرفض (مع خصم السنوية من الرصيد تلقائياً).
 */
class EmployeeLeaveController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        $own = $this->ownEmployee($companyId);
        $canManage = $this->canManage();

        if (!$canManage && !$own) {
            $this->forbidden();
            return;
        }

        View::render('employees::leaves.index', [
            'pageTitle' => 'الإجازات والأذونات',
            'leaves' => $canManage ? EmployeeLeave::forCompany($companyId) : EmployeeLeave::forEmployee((int) $own['id']),
            'own' => $own,
            'canManage' => $canManage,
            'typeLabels' => EmployeeLeave::typeLabels(),
            'statusLabels' => EmployeeLeave::statusLabels(),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        $own = $this->ownEmployee($companyId);
        $canManage = $this->canManage();

        if (!$canManage && !$own) {
            $this->forbidden();
            return;
        }

        View::render('employees::leaves.form', [
            'pageTitle' => 'طلب إجازة / إذن',
            'own' => $own,
            'canManage' => $canManage,
            // المدير يقدّم نيابة عن أي موظف؛ الموظف عن نفسه فقط
            'employees' => $canManage ? Employee::selectableManagers($companyId) : [],
            'typeLabels' => EmployeeLeave::typeLabels(),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        $own = $this->ownEmployee($companyId);
        $canManage = $this->canManage();
        if (!$canManage && !$own) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/leaves/request');

        // الموظف المعني: المدير يختار، والموظف العادي يُثبَّت على ملفه
        $employeeId = $canManage ? (int) Request::input('employee_id', 0) : (int) ($own['id'] ?? 0);
        if ($canManage && !$employeeId && $own) {
            $employeeId = (int) $own['id'];
        }
        $employee = $employeeId ? Employee::find($employeeId) : null;
        if (!$employee || (int) $employee['company_id'] !== $companyId) {
            flash_set('error', 'حدّد الموظف المعني بالطلب.');
            redirect('/employees/leaves/request');
        }

        $type = Request::input('type', 'annual');
        if (!array_key_exists($type, EmployeeLeave::typeLabels())) {
            $type = 'annual';
        }

        $start = (string) Request::input('start_date', '');
        $end = (string) Request::input('end_date', '') ?: $start;
        if ($start === '' || !strtotime($start) || !strtotime($end)) {
            flash_set('error', 'حدّد تاريخ بداية صحيحاً.');
            redirect('/employees/leaves/request');
        }
        if ($type === 'hours') {
            $end = $start; // إذن الساعات يوم واحد دائماً
        }
        if (strtotime($end) < strtotime($start)) {
            flash_set('error', 'تاريخ النهاية قبل تاريخ البداية.');
            redirect('/employees/leaves/request');
        }

        $hours = null;
        if ($type === 'hours') {
            $hours = (float) Request::input('hours', 0);
            if ($hours <= 0 || $hours > 12) {
                flash_set('error', 'حدّد عدد ساعات الإذن (بين 0.5 و12).');
                redirect('/employees/leaves/request');
            }
        }

        if (EmployeeLeave::overlaps((int) $employee['id'], $start, $end)) {
            flash_set('error', 'يوجد طلب معلّق أو إجازة معتمدة تتقاطع مع نفس الفترة.');
            redirect('/employees/leaves/request');
        }

        $days = EmployeeLeave::daysCount($start, $end);
        $leaveId = EmployeeLeave::create([
            'company_id' => $companyId,
            'employee_id' => (int) $employee['id'],
            'type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'hours' => $hours,
            'days_count' => $days,
            'reason' => mb_substr(trim((string) Request::input('reason', '')), 0, 500) ?: null,
            'created_by' => Auth::id(),
        ]);

        // تنبيه المعتمدين: المدير المباشر (إن كان مربوطاً بحساب) + مدراء الشركة
        $typeLabel = EmployeeLeave::typeLabels()[$type];
        $this->notifyApprovers(
            $companyId,
            (int) ($employee['manager_employee_id'] ?? 0),
            '🌴 طلب ' . $typeLabel,
            "{$employee['full_name']} قدّم طلب {$typeLabel} من {$start} إلى {$end}.",
            route('/employees/leaves')
        );

        ActivityLog::log('employees.leave_request', 'employee_leave', $leaveId, "طلب {$typeLabel}: {$employee['full_name']} ({$start} → {$end})");
        flash_set('success', 'تم تقديم الطلب — سيصلك إشعار عند البتّ فيه.');
        redirect('/employees/leaves');
    }

    public function approve(array $params): void
    {
        $this->decide((int) $params['id'], true);
    }

    public function reject(array $params): void
    {
        $this->decide((int) $params['id'], false);
    }

    private function decide(int $id, bool $approve): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/leaves');

        $leave = EmployeeLeave::find($id);
        if (!$leave || (int) $leave['company_id'] !== $companyId) {
            flash_set('error', 'الطلب غير موجود.');
            redirect('/employees/leaves');
        }
        if ($leave['status'] !== 'pending') {
            flash_set('error', 'سبق البتّ في هذا الطلب.');
            redirect('/employees/leaves');
        }

        $note = mb_substr(trim((string) Request::input('note', '')), 0, 255) ?: null;
        $typeLabel = EmployeeLeave::typeLabels()[$leave['type']] ?? $leave['type'];

        if ($approve) {
            // الإجازة السنوية تتطلب رصيداً كافياً (عدّل الرصيد من ملف الموظف إن لزم)
            if ($leave['type'] === 'annual' && (int) $leave['annual_leave_balance'] < (int) $leave['days_count']) {
                flash_set('error', "رصيد {$leave['full_name']} الحالي ({$leave['annual_leave_balance']} يوم) لا يكفي لخصم {$leave['days_count']} يوم — عدّل الرصيد من ملفه أولاً إن أردت الاعتماد.");
                redirect('/employees/leaves');
            }
            EmployeeLeave::approve($leave, Auth::id(), $note);
            EmployeeTimeline::add((int) $leave['employee_id'], 'leave', "{$typeLabel} معتمدة: {$leave['start_date']} → {$leave['end_date']}", $leave['start_date'], Auth::id());
            ActivityLog::log('employees.leave_approve', 'employee_leave', $id, "اعتماد {$typeLabel}: {$leave['full_name']}");
        } else {
            EmployeeLeave::reject($id, Auth::id(), $note);
            ActivityLog::log('employees.leave_reject', 'employee_leave', $id, "رفض {$typeLabel}: {$leave['full_name']}");
        }

        // تنبيه صاحب الطلب إن كان مربوطاً بحساب
        if (!empty($leave['linked_user_id'])) {
            Notification::send(
                (int) $leave['linked_user_id'],
                $approve ? '✅ اعتُمد طلبك: ' . $typeLabel : '❌ رُفض طلبك: ' . $typeLabel,
                ($approve ? 'اعتُمد طلبك من ' : 'رُفض طلبك من ') . $leave['start_date'] . ' إلى ' . $leave['end_date'] . ($note ? " — {$note}" : ''),
                route('/employees/leaves')
            );
        }

        flash_set('success', $approve ? 'تم اعتماد الطلب وخصم الرصيد (للسنوية).' : 'تم رفض الطلب.');
        redirect('/employees/leaves');
    }

    // ---------------------------------------------------------------

    /** ملف الموظف المربوط بحساب المستخدم الحالي (إن وُجد). */
    private function ownEmployee(int $companyId): ?array
    {
        return Database::first(
            'SELECT * FROM employees_profiles WHERE company_id = :c AND linked_user_id = :u',
            ['c' => $companyId, 'u' => Auth::id()]
        );
    }

    /** تنبيه المدير المباشر لصاحب الطلب + مدراء الشركة (دون تكرار). */
    private function notifyApprovers(int $companyId, int $managerEmployeeId, string $title, string $body, string $url): void
    {
        $userIds = [];
        if ($managerEmployeeId) {
            $mgr = Employee::find($managerEmployeeId);
            if ($mgr && !empty($mgr['linked_user_id'])) {
                $userIds[(int) $mgr['linked_user_id']] = true;
            }
        }
        foreach (Database::select(
            "SELECT id FROM users WHERE company_id = :c AND membership_type = 'company_admin' AND status = 'active'",
            ['c' => $companyId]
        ) as $admin) {
            $userIds[(int) $admin['id']] = true;
        }
        unset($userIds[Auth::id()]); // لا يُنبَّه مقدّم الطلب نفسه
        foreach (array_keys($userIds) as $uid) {
            Notification::send($uid, $title, $body, $url);
        }
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin()
            || Permission::check('employees.manage') || Permission::check('employees.edit');
    }

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('employees::no-company', ['pageTitle' => 'الإجازات']);
            exit;
        }
        return $companyId;
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

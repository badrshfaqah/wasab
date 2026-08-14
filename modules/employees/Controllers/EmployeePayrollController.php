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

/**
 * مسير الرواتب المبسّط: توليد مسير شهري (أساسي + بدلات − خصم أيام الإجازات بدون
 * راتب)، تعديل بنوده قبل الاعتماد، ثم اعتماد يقفل المسير ويُشعر الموظفين، مع
 * صفحة طباعة A4. البيانات حساسة: لمن يرى الرواتب (view_sensitive/manage) فقط.
 */
class EmployeePayrollController
{
    public function index(): void
    {
        $companyId = $this->requireAccess();
        View::render('employees::payroll.index', [
            'pageTitle' => 'مسير الرواتب',
            'runs' => Database::select(
                'SELECT r.*, u.name AS created_by_name,
                        (SELECT COUNT(*) FROM employees_payroll_items i WHERE i.run_id = r.id) AS items_count,
                        (SELECT COALESCE(SUM(i.net), 0) FROM employees_payroll_items i WHERE i.run_id = r.id) AS total_net
                   FROM employees_payroll_runs r LEFT JOIN users u ON u.id = r.created_by
                  WHERE r.company_id = :c ORDER BY r.month DESC',
                ['c' => $companyId]
            ),
        ]);
    }

    /** توليد مسير شهر: بند لكل موظف نشط، بخصم تلقائي لأيام "بدون راتب". */
    public function generate(): void
    {
        $companyId = $this->requireAccess();
        $this->verifyCsrf('/employees/payroll');

        $month = (string) Request::input('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            flash_set('error', 'حدّد شهراً صحيحاً.');
            redirect('/employees/payroll');
        }
        if (Database::first('SELECT id FROM employees_payroll_runs WHERE company_id = :c AND month = :m', ['c' => $companyId, 'm' => $month])) {
            flash_set('error', "يوجد مسير لشهر {$month} بالفعل.");
            redirect('/employees/payroll');
        }

        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));
        $runId = Database::insert('employees_payroll_runs', [
            'company_id' => $companyId,
            'month' => $month,
            'status' => 'draft',
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $employees = Database::select(
            "SELECT * FROM employees_profiles WHERE company_id = :c AND status != 'terminated' ORDER BY full_name",
            ['c' => $companyId]
        );
        $count = 0;
        foreach ($employees as $emp) {
            $base = (float) ($emp['salary_base'] ?? 0);
            $allow = (float) ($emp['salary_allowances'] ?? 0);

            // خصم الإجازات بدون راتب: أيام التقاطع مع الشهر × (الأساسي ÷ 30)
            $unpaidDays = 0;
            foreach (Database::select(
                "SELECT start_date, end_date FROM employees_leaves
                  WHERE employee_id = :e AND status = 'approved' AND type = 'unpaid'
                    AND start_date <= :to AND end_date >= :from",
                ['e' => $emp['id'], 'from' => $from, 'to' => $to]
            ) as $lv) {
                $s = max(strtotime($lv['start_date']), strtotime($from));
                $t = min(strtotime($lv['end_date']), strtotime($to));
                $unpaidDays += (int) (($t - $s) / 86400) + 1;
            }
            $deduction = $unpaidDays > 0 ? round($unpaidDays * ($base / 30), 2) : 0.0;

            Database::insert('employees_payroll_items', [
                'run_id' => $runId,
                'employee_id' => (int) $emp['id'],
                'base_salary' => $base,
                'allowances' => $allow,
                'deductions' => $deduction,
                'deduction_note' => $unpaidDays > 0 ? "خصم {$unpaidDays} يوم إجازة بدون راتب" : null,
                'net' => round($base + $allow - $deduction, 2),
            ]);
            $count++;
        }

        ActivityLog::log('employees.payroll_generate', 'payroll_run', $runId, "توليد مسير رواتب {$month} ({$count} موظفاً)");
        flash_set('success', "وُلّد مسير {$month} لـ{$count} موظفاً — راجع البنود ثم اعتمده.");
        redirect('/employees/payroll/' . $runId);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireAccess();
        $run = $this->findRun((int) $params['id'], $companyId);
        View::render('employees::payroll.show', [
            'pageTitle' => 'مسير ' . $run['month'],
            'run' => $run,
            'items' => $this->items((int) $run['id']),
        ]);
    }

    /** تعديل بند (بدلات/خصومات/ملاحظة) قبل الاعتماد. */
    public function updateItem(array $params): void
    {
        $companyId = $this->requireAccess();
        $run = $this->findRun((int) $params['id'], $companyId);
        $this->verifyCsrf('/employees/payroll/' . $run['id']);
        if ($run['status'] !== 'draft') {
            flash_set('error', 'المسير معتمد — لا يُعدَّل.');
            redirect('/employees/payroll/' . $run['id']);
        }
        $item = Database::first('SELECT * FROM employees_payroll_items WHERE id = :i AND run_id = :r', ['i' => (int) $params['itemId'], 'r' => $run['id']]);
        if (!$item) {
            flash_set('error', 'البند غير موجود.');
            redirect('/employees/payroll/' . $run['id']);
        }

        $allow = round((float) Request::input('allowances', 0), 2);
        $ded = round((float) Request::input('deductions', 0), 2);
        Database::update('employees_payroll_items', [
            'allowances' => $allow,
            'deductions' => $ded,
            'deduction_note' => mb_substr(trim((string) Request::input('deduction_note', '')), 0, 255) ?: null,
            'net' => round((float) $item['base_salary'] + $allow - $ded, 2),
        ], 'id = :id', ['id' => $item['id']]);

        flash_set('success', 'حُدّث البند.');
        redirect('/employees/payroll/' . $run['id']);
    }

    /** اعتماد المسير: يقفله ويُشعر الموظفين المربوطين بحساباتهم بكشوفهم. */
    public function approve(array $params): void
    {
        $companyId = $this->requireAccess();
        $run = $this->findRun((int) $params['id'], $companyId);
        $this->verifyCsrf('/employees/payroll/' . $run['id']);
        if ($run['status'] !== 'draft') {
            flash_set('error', 'المسير معتمد بالفعل.');
            redirect('/employees/payroll/' . $run['id']);
        }

        Database::update('employees_payroll_runs', [
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $run['id']]);

        foreach ($this->items((int) $run['id']) as $item) {
            if (!empty($item['linked_user_id'])) {
                Notification::send((int) $item['linked_user_id'], '💵 كشف راتبك جاهز', 'اعتُمد مسير رواتب شهر ' . $run['month'] . '.', route('/me'));
            }
        }

        ActivityLog::log('employees.payroll_approve', 'payroll_run', (int) $run['id'], "اعتماد مسير {$run['month']}");
        flash_set('success', 'اعتُمد المسير وأُشعر الموظفون.');
        redirect('/employees/payroll/' . $run['id']);
    }

    /** حذف مسير غير معتمد (لإعادة التوليد). */
    public function destroy(array $params): void
    {
        $companyId = $this->requireAccess();
        $run = $this->findRun((int) $params['id'], $companyId);
        $this->verifyCsrf('/employees/payroll');
        if ($run['status'] !== 'draft') {
            flash_set('error', 'لا يُحذف مسير معتمد.');
            redirect('/employees/payroll/' . $run['id']);
        }
        Database::delete('employees_payroll_runs', 'id = :id', ['id' => $run['id']]);
        flash_set('success', 'حُذف المسير — أعد توليده متى شئت.');
        redirect('/employees/payroll');
    }

    /** صفحة طباعة المسير (A4). */
    public function print(array $params): void
    {
        $companyId = $this->requireAccess();
        $run = $this->findRun((int) $params['id'], $companyId);
        View::render('employees::payroll.print', [
            'pageTitle' => 'مسير ' . $run['month'],
            'run' => $run,
            'items' => $this->items((int) $run['id']),
            'company' => Database::first('SELECT name FROM companies WHERE id = :id', ['id' => $companyId]),
        ], '');
    }

    // ---------------------------------------------------------------

    private function items(int $runId): array
    {
        return Database::select(
            'SELECT i.*, e.full_name, e.job_title, e.linked_user_id
               FROM employees_payroll_items i JOIN employees_profiles e ON e.id = i.employee_id
              WHERE i.run_id = :r ORDER BY e.full_name',
            ['r' => $runId]
        );
    }

    private function findRun(int $id, int $companyId): array
    {
        $run = Database::first('SELECT * FROM employees_payroll_runs WHERE id = :id', ['id' => $id]);
        if (!$run || (int) $run['company_id'] !== $companyId) {
            flash_set('error', 'المسير غير موجود.');
            redirect('/employees/payroll');
        }
        return $run;
    }

    private function requireAccess(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('employees::no-company', ['pageTitle' => 'مسير الرواتب']);
            exit;
        }
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin()
            && !Permission::check('employees.manage') && !Permission::check('employees.view_sensitive')) {
            http_response_code(403);
            View::render('errors/403', [], '');
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
}

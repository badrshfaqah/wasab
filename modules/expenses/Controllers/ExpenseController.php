<?php

namespace Modules\Expenses\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Notification;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;

/**
 * المصروفات: الموظف يقدّم طلباً (مبلغ + تاريخ + وصف + صورة فاتورة اختيارية)،
 * ومن يملك الإدارة يعتمد أو يرفض مع تنبيهات، مع إجمالي شهري أعلى القائمة.
 */
class ExpenseController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        $canManage = $this->canManage();
        if (!$canManage && !$this->can('expenses.submit')) {
            $this->forbidden();
            return;
        }

        $month = (string) Request::query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $where = 'x.company_id = :c AND x.expense_date BETWEEN :f AND :t';
        $params = ['c' => $companyId, 'f' => $from, 't' => $to];
        if (!$canManage) {
            $where .= ' AND x.user_id = ' . Auth::id();
        }

        $rows = Database::select(
            "SELECT x.*, u.name AS user_name, d.name AS decided_by_name
               FROM expenses_claims x
               JOIN users u ON u.id = x.user_id
               LEFT JOIN users d ON d.id = x.decided_by
              WHERE {$where}
              ORDER BY (x.status = 'pending') DESC, x.id DESC LIMIT 300",
            $params
        );

        $approvedTotal = 0.0;
        foreach ($rows as $r) {
            if ($r['status'] === 'approved') {
                $approvedTotal += (float) $r['amount'];
            }
        }

        View::render('expenses::index', [
            'pageTitle' => 'المصروفات',
            'rows' => $rows,
            'month' => $month,
            'approvedTotal' => $approvedTotal,
            'canManage' => $canManage,
            'canSubmit' => $this->can('expenses.submit') || $canManage,
        ]);
    }

    public function create(): void
    {
        $this->requireCompanyContext();
        if (!$this->can('expenses.submit') && !$this->canManage()) {
            $this->forbidden();
            return;
        }
        View::render('expenses::form', ['pageTitle' => 'طلب مصروف']);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('expenses.submit') && !$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/expenses/new');

        $amount = round((float) Request::input('amount', 0), 2);
        $description = mb_substr(trim((string) Request::input('description', '')), 0, 500);
        $date = (string) Request::input('expense_date', date('Y-m-d'));
        if ($amount <= 0 || $description === '' || !strtotime($date)) {
            flash_set('error', 'أدخل مبلغاً صحيحاً ووصفاً وتاريخاً.');
            redirect('/expenses/new');
        }

        $receipt = Uploads::handleImage('receipt', BASE_PATH . '/storage/uploads/expenses/' . $companyId);
        if ($receipt['error']) {
            flash_set('error', $receipt['error']);
            redirect('/expenses/new');
        }

        $claimId = Database::insert('expenses_claims', [
            'company_id' => $companyId,
            'user_id' => Auth::id(),
            'amount' => $amount,
            'expense_date' => date('Y-m-d', strtotime($date)),
            'description' => $description,
            'receipt_image' => $receipt['filename'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // تنبيه مدراء الشركة
        foreach (Database::select(
            "SELECT id FROM users WHERE company_id = :c AND membership_type = 'company_admin' AND status = 'active' AND id != :me",
            ['c' => $companyId, 'me' => Auth::id()]
        ) as $admin) {
            Notification::send((int) $admin['id'], '💰 طلب مصروف جديد', Auth::user()['name'] . ' — ' . number_format($amount, 2), route('/expenses'));
        }

        ActivityLog::log('expenses.submit', 'expense_claim', $claimId, 'طلب مصروف: ' . number_format($amount, 2));
        flash_set('success', 'قُدّم طلب المصروف — سيصلك إشعار بالقرار.');
        redirect('/expenses');
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
        $this->verifyCsrf('/expenses');

        $claim = Database::first('SELECT * FROM expenses_claims WHERE id = :id AND company_id = :c', ['id' => $id, 'c' => $companyId]);
        if (!$claim || $claim['status'] !== 'pending') {
            flash_set('error', 'الطلب غير موجود أو سبق البتّ فيه.');
            redirect('/expenses');
        }

        $note = mb_substr(trim((string) Request::input('note', '')), 0, 255) ?: null;
        Database::update('expenses_claims', [
            'status' => $approve ? 'approved' : 'rejected',
            'decided_by' => Auth::id(),
            'decided_at' => date('Y-m-d H:i:s'),
            'decision_note' => $note,
        ], 'id = :id', ['id' => $id]);

        Notification::send(
            (int) $claim['user_id'],
            $approve ? '✅ اعتُمد مصروفك' : '❌ رُفض مصروفك',
            number_format((float) $claim['amount'], 2) . ($note ? " — {$note}" : ''),
            route('/expenses')
        );
        ActivityLog::log($approve ? 'expenses.approve' : 'expenses.reject', 'expense_claim', $id, ($approve ? 'اعتماد' : 'رفض') . ' مصروف: ' . number_format((float) $claim['amount'], 2));
        flash_set('success', $approve ? 'اعتُمد المصروف.' : 'رُفض المصروف.');
        redirect('/expenses');
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('expenses::no-company', ['pageTitle' => 'المصروفات']);
            exit;
        }
        return $companyId;
    }

    private function can(string $key): bool
    {
        return Permission::check($key);
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('expenses.manage');
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

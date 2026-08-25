<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Notification;
use App\Core\Permission;
use Modules\Mobileapi\Support\Api;

/**
 * مركز الموافقات للجوال: يجمع كل ما ينتظر قرار المستخدم من الوحدات المفعّلة،
 * ويسمح بالبتّ في البنود التي لا شاشة مستقلة لها في التطبيق (الإجازات،
 * المصروفات، طلبات الخطابات). أما المهام والاجتماعات والمستندات فتُفتح
 * بشاشاتها الخاصة، ولذلك نعيد لها `path` بدل أزرار قرار.
 *
 * منطق التجميع مطابق لـ App\Controllers\ApprovalsController على الويب، وكل
 * قسم معزول فلا يُسقط خطأُ قسمٍ الرد كله.
 */
class ApprovalsApiController
{
    /** GET /api/v1/approvals - كل الأقسام مع بنودها. */
    public function index(): void
    {
        $user = Api::user();
        $companyId = (int) (Auth::companyId() ?? 0);
        if (!$companyId) {
            Api::ok(['total' => 0, 'sections' => []]);
        }

        $sections = array_values(array_filter($this->collect($companyId, (int) $user['id'])));
        $total = array_sum(array_map(fn (array $s) => count($s['rows']), $sections));

        Api::ok(['total' => $total, 'sections' => $sections]);
    }

    /**
     * POST /api/v1/approvals/{type}/{id}/decide  {action: approve|reject, note}
     * type: leaves | expenses | letters
     */
    public function decide(array $params): void
    {
        $companyId = (int) (Auth::companyId() ?? 0);
        if (!$companyId) {
            Api::error('لا توجد شركة مرتبطة بحسابك.', 400, 'no_company');
        }

        $action = (string) Api::input('action', '');
        if (!in_array($action, ['approve', 'reject'], true)) {
            Api::error('الإجراء غير معروف.', 422, 'invalid_action');
        }
        $approve = $action === 'approve';
        $note = mb_substr(trim((string) Api::input('note', '')), 0, 255) ?: null;
        $id = (int) ($params['id'] ?? 0);

        switch ((string) ($params['type'] ?? '')) {
            case 'leaves':
                $this->decideLeave($companyId, $id, $approve, $note);
                break;
            case 'expenses':
                $this->decideExpense($companyId, $id, $approve, $note);
                break;
            case 'letters':
                $this->decideLetterRequest($companyId, $id, $approve, $note);
                break;
            default:
                Api::error('نوع الطلب غير معروف.', 404, 'unknown_type');
        }
    }

    // ---------------------------------------------------------------- التجميع

    private function collect(int $companyId, int $userId): array
    {
        $safe = function (callable $fn) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                log_exception($e);
                return null;
            }
        };
        $isAdmin = Auth::isSystemAdmin() || Auth::isCompanyAdmin();
        $sections = [];

        // طلبات الإجازة - قرار داخل التطبيق
        if (ModuleManager::isActive('employees')
            && ($isAdmin || Permission::check('employees.manage') || Permission::check('employees.edit'))) {
            $sections['leaves'] = $safe(fn () => [
                'key' => 'leaves',
                'title' => 'طلبات إجازة',
                'icon' => '🌴',
                'decidable' => true,
                'rows' => array_map(fn (array $r) => [
                    'id' => (int) $r['id'],
                    'label' => $r['full_name'],
                    'meta' => $r['start_date'] . ' ← ' . $r['end_date'],
                    'detail' => trim(($this->leaveTypeLabel($r['type'])) . ' · ' . (int) $r['days_count'] . ' يوم'),
                    'path' => null,
                ], Database::select(
                    "SELECT l.id, l.type, l.days_count, l.start_date, l.end_date, e.full_name
                       FROM employees_leaves l JOIN employees_profiles e ON e.id = l.employee_id
                      WHERE l.company_id = :c AND l.status = 'pending' ORDER BY l.id LIMIT 20",
                    ['c' => $companyId]
                )),
            ]);
        }

        // مستندات جاهزة للتوقيع - تُفتح بشاشة المستند
        if (ModuleManager::isActive('documents') && ($isAdmin || Permission::check('documents.sign'))) {
            $sections['doc_sign'] = $safe(fn () => [
                'key' => 'doc_sign',
                'title' => 'مستندات جاهزة للتوقيع',
                'icon' => '✍️',
                'decidable' => false,
                'rows' => array_map(fn (array $r) => [
                    'id' => (int) $r['id'],
                    'label' => $r['title'],
                    'meta' => $r['number'] ?? '',
                    'detail' => null,
                    'path' => 'documents/' . (int) $r['id'],
                ], Database::select(
                    "SELECT id, title, number FROM documents_documents
                      WHERE company_id = :c AND status = 'approved' ORDER BY id DESC LIMIT 20",
                    ['c' => $companyId]
                )),
            ]);
        }

        // مهام تنتظر اعتماد المستخدم - تُفتح بشاشة المهمة
        if (ModuleManager::isActive('tasks')) {
            $sections['tasks'] = $safe(fn () => [
                'key' => 'tasks',
                'title' => 'مهام تنتظر اعتمادك',
                'icon' => '📋',
                'decidable' => false,
                'rows' => array_map(fn (array $r) => [
                    'id' => (int) $r['id'],
                    'label' => $r['title'],
                    'meta' => '',
                    'detail' => null,
                    'path' => 'tasks/' . (int) $r['id'],
                ], Database::select(
                    "SELECT id, title FROM tasks_tasks
                      WHERE company_id = :c AND requires_approval = 1 AND approved_at IS NULL
                        AND approver_id = :me AND status = 'in_review'
                      ORDER BY id DESC LIMIT 20",
                    ['c' => $companyId, 'me' => $userId]
                )),
            ]);
        }

        // دعوات اجتماعات - تُفتح بشاشة الاجتماع
        if (ModuleManager::isActive('meetings')) {
            $sections['meetings'] = $safe(fn () => [
                'key' => 'meetings',
                'title' => 'دعوات اجتماعات بانتظار ردّك',
                'icon' => '📅',
                'decidable' => false,
                'rows' => array_map(fn (array $r) => [
                    'id' => (int) $r['id'],
                    'label' => $r['title'],
                    'meta' => $r['meta'],
                    'detail' => null,
                    'path' => 'meetings/' . (int) $r['id'],
                ], Database::select(
                    "SELECT m.id, m.title, DATE_FORMAT(m.starts_at, '%Y-%m-%d %H:%i') AS meta
                       FROM meetings_attendees a JOIN meetings_meetings m ON m.id = a.meeting_id
                      WHERE m.company_id = :c AND a.user_id = :me AND a.response = 'pending'
                        AND m.status = 'scheduled' AND m.starts_at >= NOW()
                      ORDER BY m.starts_at LIMIT 20",
                    ['c' => $companyId, 'me' => $userId]
                )),
            ]);
        }

        // طلبات خطابات - قرار داخل التطبيق
        if (ModuleManager::isActive('forms') && ($isAdmin || Permission::check('forms.manage'))) {
            $sections['letters'] = $safe(fn () => [
                'key' => 'letters',
                'title' => 'طلبات خطابات',
                'icon' => '📨',
                'decidable' => true,
                'rows' => array_map(fn (array $r) => [
                    'id' => (int) $r['id'],
                    'label' => $r['full_name'],
                    'meta' => $r['template_name'],
                    'detail' => null,
                    'path' => null,
                ], Database::select(
                    "SELECT r.id, e.full_name, t.name AS template_name
                       FROM forms_requests r
                       JOIN employees_profiles e ON e.id = r.employee_id
                       JOIN forms_templates t ON t.id = r.template_id
                      WHERE r.company_id = :c AND r.status = 'pending' ORDER BY r.id LIMIT 20",
                    ['c' => $companyId]
                )),
            ]);
        }

        // مصروفات - قرار داخل التطبيق
        if (ModuleManager::isActive('expenses') && ($isAdmin || Permission::check('expenses.manage'))) {
            $sections['expenses'] = $safe(fn () => [
                'key' => 'expenses',
                'title' => 'مصروفات بانتظار الاعتماد',
                'icon' => '💰',
                'decidable' => true,
                'rows' => array_map(fn (array $r) => [
                    'id' => (int) $r['id'],
                    'label' => $r['name'],
                    'meta' => number_format((float) $r['amount'], 2),
                    'detail' => $r['expense_date'] . ' · ' . mb_substr((string) $r['description'], 0, 80),
                    'path' => null,
                ], Database::select(
                    "SELECT x.id, x.amount, x.expense_date, x.description, u.name
                       FROM expenses_claims x JOIN users u ON u.id = x.user_id
                      WHERE x.company_id = :c AND x.status = 'pending' ORDER BY x.id LIMIT 20",
                    ['c' => $companyId]
                )),
            ]);
        }

        return $sections;
    }

    private function leaveTypeLabel(string $type): string
    {
        if (class_exists(\Modules\Employees\Models\EmployeeLeave::class)) {
            return \Modules\Employees\Models\EmployeeLeave::typeLabels()[$type] ?? $type;
        }
        return $type;
    }

    // ---------------------------------------------------------------- القرارات

    /** يعيد منطق EmployeeLeaveController::decide نفسه (بلا CSRF ولا redirect). */
    private function decideLeave(int $companyId, int $id, bool $approve, ?string $note): void
    {
        if (!ModuleManager::isActive('employees')) {
            Api::error('وحدة الملف الوظيفي غير مفعلة.', 404, 'module_inactive');
        }
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin()
            && !Permission::check('employees.manage') && !Permission::check('employees.edit')) {
            Api::error('لا تملك صلاحية البتّ في طلبات الإجازة.', 403, 'forbidden');
        }

        $leaveClass = \Modules\Employees\Models\EmployeeLeave::class;
        $leave = $leaveClass::find($id);
        if (!$leave || (int) $leave['company_id'] !== $companyId) {
            Api::error('الطلب غير موجود.', 404, 'not_found');
        }
        if ($leave['status'] !== 'pending') {
            Api::error('سبق البتّ في هذا الطلب.', 409, 'already_decided');
        }

        $typeLabel = $leaveClass::typeLabels()[$leave['type']] ?? $leave['type'];

        if ($approve) {
            // الإجازة السنوية تتطلب رصيداً كافياً - نفس شرط الويب.
            if ($leave['type'] === 'annual'
                && (int) $leave['annual_leave_balance'] < (int) $leave['days_count']) {
                Api::error(
                    "رصيد {$leave['full_name']} الحالي ({$leave['annual_leave_balance']} يوم) لا يكفي لخصم "
                        . "{$leave['days_count']} يوم — عدّل الرصيد من ملفه على الويب أولاً.",
                    409,
                    'insufficient_balance'
                );
            }
            $leaveClass::approve($leave, (int) Auth::id(), $note);
            \Modules\Employees\Models\EmployeeTimeline::add(
                (int) $leave['employee_id'],
                'leave',
                "{$typeLabel} معتمدة: {$leave['start_date']} → {$leave['end_date']}",
                $leave['start_date'],
                (int) Auth::id()
            );
            ActivityLog::log('employees.leave_approve', 'employee_leave', $id, "اعتماد {$typeLabel}: {$leave['full_name']}");
        } else {
            $leaveClass::reject($id, (int) Auth::id(), $note);
            ActivityLog::log('employees.leave_reject', 'employee_leave', $id, "رفض {$typeLabel}: {$leave['full_name']}");
        }

        if (!empty($leave['linked_user_id'])) {
            Notification::send(
                (int) $leave['linked_user_id'],
                $approve ? '✅ اعتُمد طلبك: ' . $typeLabel : '❌ رُفض طلبك: ' . $typeLabel,
                ($approve ? 'اعتُمد طلبك من ' : 'رُفض طلبك من ') . $leave['start_date']
                    . ' إلى ' . $leave['end_date'] . ($note ? " — {$note}" : ''),
                route('/employees/leaves')
            );
        }

        Api::ok(['message' => $approve ? 'تم اعتماد الطلب.' : 'تم رفض الطلب.']);
    }

    private function decideExpense(int $companyId, int $id, bool $approve, ?string $note): void
    {
        if (!ModuleManager::isActive('expenses')) {
            Api::error('وحدة المصروفات غير مفعلة.', 404, 'module_inactive');
        }
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin() && !Permission::check('expenses.manage')) {
            Api::error('لا تملك صلاحية اعتماد المصروفات.', 403, 'forbidden');
        }

        $claim = Database::first(
            'SELECT * FROM expenses_claims WHERE id = :id AND company_id = :c',
            ['id' => $id, 'c' => $companyId]
        );
        if (!$claim) {
            Api::error('الطلب غير موجود.', 404, 'not_found');
        }
        if ($claim['status'] !== 'pending') {
            Api::error('سبق البتّ في هذا الطلب.', 409, 'already_decided');
        }

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
        ActivityLog::log(
            $approve ? 'expenses.approve' : 'expenses.reject',
            'expense_claim',
            $id,
            ($approve ? 'اعتماد' : 'رفض') . ' مصروف: ' . number_format((float) $claim['amount'], 2)
        );

        Api::ok(['message' => $approve ? 'اعتُمد المصروف.' : 'رُفض المصروف.']);
    }

    /** الاعتماد يولّد الخطاب فعلياً كما في FormController::approveRequest. */
    private function decideLetterRequest(int $companyId, int $id, bool $approve, ?string $note): void
    {
        if (!ModuleManager::isActive('forms')) {
            Api::error('وحدة النماذج غير مفعلة.', 404, 'module_inactive');
        }
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin() && !Permission::check('forms.manage')) {
            Api::error('لا تملك صلاحية البتّ في طلبات الخطابات.', 403, 'forbidden');
        }

        $req = Database::first(
            'SELECT r.*, e.linked_user_id, t.name AS template_name FROM forms_requests r
               JOIN employees_profiles e ON e.id = r.employee_id
               JOIN forms_templates t ON t.id = r.template_id
              WHERE r.id = :id AND r.company_id = :c',
            ['id' => $id, 'c' => $companyId]
        );
        if (!$req) {
            Api::error('الطلب غير موجود.', 404, 'not_found');
        }
        if ($req['status'] !== 'pending') {
            Api::error('سبق البتّ في هذا الطلب.', 409, 'already_decided');
        }

        if (!$approve) {
            Database::update('forms_requests', [
                'status' => 'rejected',
                'decided_by' => Auth::id(),
                'decided_at' => date('Y-m-d H:i:s'),
                'decision_note' => $note,
            ], 'id = :id', ['id' => $id]);

            if (!empty($req['linked_user_id'])) {
                Notification::send(
                    (int) $req['linked_user_id'],
                    '❌ رُفض طلب الخطاب',
                    $req['template_name'] . ($note ? ' — ' . $note : ''),
                    route('/forms/requests')
                );
            }
            Api::ok(['message' => 'رُفض الطلب.']);
        }

        $template = \Modules\Forms\Models\FormTemplate::find((int) $req['template_id']);
        $employee = Database::first('SELECT * FROM employees_profiles WHERE id = :id', ['id' => (int) $req['employee_id']]);
        if (!$template || !$employee) {
            Api::error('القالب أو الموظف لم يعد موجوداً.', 404, 'not_found');
        }

        $companyName = (string) (Database::first('SELECT name FROM companies WHERE id = :id', ['id' => $companyId])['name'] ?? '');
        $values = \Modules\Forms\Models\MergeFields::knownValues($companyId, (int) $employee['id'], $companyName);
        $finalBody = \Modules\Forms\Models\MergeFields::render($template['body'], $values);
        $number = \Modules\Forms\Models\FormLetter::nextNumber($companyId);

        $letterId = \Modules\Forms\Models\FormLetter::create([
            'company_id' => $companyId,
            'template_id' => (int) $template['id'],
            'title' => $template['name'],
            'number' => $number,
            'employee_id' => (int) $employee['id'],
            'recipient_name' => mb_substr($employee['full_name'], 0, 180),
            'body' => $finalBody,
            'qr_enabled' => !empty($template['qr_enabled']) ? 1 : 0,
            'verify_token' => bin2hex(random_bytes(16)),
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Database::update('forms_requests', [
            'status' => 'done',
            'letter_id' => $letterId,
            'decided_by' => Auth::id(),
            'decided_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);

        if (ModuleManager::isActive('employees') && !empty($employee['linked_user_id'])) {
            Notification::send(
                (int) $employee['linked_user_id'],
                '📄 صدر خطابك',
                $template['name'] . ' (رقم ' . $number . ')',
                route('/forms/' . $letterId)
            );
        }

        ActivityLog::log(
            'forms.request_approve',
            'form_letter',
            $letterId,
            "إصدار خطاب بطلب ذاتي: {$template['name']} - {$employee['full_name']}"
        );

        Api::ok(['message' => 'صدر الخطاب برقم ' . $number . ' وأُشعر الموظف.']);
    }
}

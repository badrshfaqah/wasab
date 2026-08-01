<?php

namespace Modules\Forms\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;
use Modules\Forms\Models\FormLetter;
use Modules\Forms\Models\FormSetting;
use Modules\Forms\Models\FormTemplate;
use Modules\Forms\Models\MergeFields;

/**
 * توليد خطابات الموارد البشرية من قوالب بحقول دمج. التدفق:
 *  اختيار قالب + (موظف اختياري) => ملء الحقول المعروفة تلقائياً وطلب اليدوية
 *  => معاينة النص النهائي => حفظ خطاب مرقّم => طباعة/PDF بالترويسة والتوقيع.
 */
class FormController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('forms.view')) {
            $this->forbidden();
            return;
        }
        $filters = [];
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        $page = max(1, (int) Request::query('page', 1));
        $result = FormLetter::paginate($companyId, $page, 20, $filters);

        View::render('forms::index', [
            'pageTitle' => 'النماذج',
            'letters' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'filters' => $filters,
            'templates' => FormTemplate::forCompany($companyId, true),
            'canGenerate' => $this->can('forms.generate'),
            'canManage' => $this->canManage(),
            'canDelete' => $this->can('forms.delete'),
        ]);
    }

    /** الخطوة 1: اختيار القالب والموظف → عرض نموذج التعبئة (المعروف مملوء، اليدوي فارغ). */
    public function generate(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('forms.generate')) {
            $this->forbidden();
            return;
        }

        $templateId = (int) Request::query('template', 0);
        $template = $templateId ? FormTemplate::find($templateId) : null;
        if (!$template || (int) $template['company_id'] !== $companyId) {
            flash_set('error', 'اختر قالباً صحيحاً.');
            redirect('/forms');
        }

        $employeeId = (int) Request::query('employee', 0) ?: null;
        $companyName = $this->companyName($companyId);
        $known = MergeFields::knownValues($companyId, $employeeId, $companyName);
        $manual = MergeFields::manualFields($template['body'], $known);

        View::render('forms::generate', [
            'pageTitle' => 'توليد: ' . $template['name'],
            'template' => $template,
            'employees' => $this->employeeOptions($companyId),
            'employeeId' => $employeeId,
            'known' => $known,
            'manualFields' => $manual,
            'employeesActive' => ModuleManager::isActive('employees'),
        ]);
    }

    /** الخطوة 2: حفظ الخطاب بعد ملء الحقول (المعروفة + اليدوية) وترقيمه. */
    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('forms.generate')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/forms');

        $templateId = (int) Request::input('template_id', 0);
        $template = $templateId ? FormTemplate::find($templateId) : null;
        if (!$template || (int) $template['company_id'] !== $companyId) {
            flash_set('error', 'قالب غير صحيح.');
            redirect('/forms');
        }

        $employeeId = (int) Request::input('employee_id', 0) ?: null;
        $companyName = $this->companyName($companyId);
        $known = MergeFields::knownValues($companyId, $employeeId, $companyName);

        // دمج القيم اليدوية المُدخلة فوق المعروفة (اليدوي يملأ ما نقص)
        $manualInput = (array) ($_POST['fields'] ?? []);
        $values = $known;
        foreach ($manualInput as $key => $val) {
            $key = trim((string) $key);
            if ($key !== '' && (!isset($values[$key]) || $values[$key] === '')) {
                $values[$key] = trim((string) $val);
            }
        }

        $finalBody = MergeFields::render($template['body'], $values);
        $recipient = $values['الاسم'] ?? ($values['الجهة'] ?? null);

        $number = FormLetter::nextNumber($companyId);
        $letterId = FormLetter::create([
            'company_id' => $companyId,
            'template_id' => $templateId,
            'title' => $template['name'],
            'number' => $number,
            'employee_id' => $employeeId,
            'recipient_name' => $recipient ? mb_substr($recipient, 0, 180) : null,
            'body' => $finalBody,
            'verify_token' => bin2hex(random_bytes(16)),
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // أرشفة تلقائية بملف الموظف: تسجيل حدث بالسجل الزمني إن كان الخطاب لموظف والإضافة مفعّلة
        if ($employeeId && \App\Core\ModuleManager::isActive('employees')) {
            \Modules\Employees\Models\EmployeeTimeline::add(
                $employeeId,
                'document',
                "صدر خطاب: {$template['name']} (رقم {$number})",
                date('Y-m-d'),
                Auth::id()
            );
        }

        ActivityLog::log('forms.generate', 'form_letter', $letterId, "توليد خطاب: {$template['name']}" . ($recipient ? " - {$recipient}" : ''));
        flash_set('success', 'تم توليد الخطاب برقم ' . $number . '.');
        redirect('/forms/' . $letterId);
    }

    /** صفحة تحقّق عامة من صحة خطاب عبر رمزه (بلا مصادقة، بلا عرض المحتوى). */
    public function verify(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $letter = ctype_xdigit($token) ? FormLetter::findByToken($token) : null;

        View::render('forms::verify', [
            'pageTitle' => 'التحقق من خطاب',
            'letter' => $letter,
        ], '');
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('forms.view')) {
            $this->forbidden();
            return;
        }
        $letter = $this->findVisible((int) $params['id'], $companyId);

        View::render('forms::show', [
            'pageTitle' => $letter['title'] . ' - ' . $letter['number'],
            'letter' => $letter,
            'canDelete' => $this->can('forms.delete'),
        ]);
    }

    /** طباعة/PDF بالترويسة والخلفية والتوقيع والختم. */
    public function print(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('forms.view')) {
            $this->forbidden();
            return;
        }
        $letter = $this->findVisible((int) $params['id'], $companyId);
        $settings = FormSetting::getOrCreate($companyId);

        View::render('forms::print', [
            'pageTitle' => $letter['title'],
            'letter' => $letter,
            'settings' => $settings,
        ], '');
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('forms.delete')) {
            $this->forbidden();
            return;
        }
        $letter = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/forms/' . $letter['id']);

        FormLetter::delete((int) $letter['id']);
        ActivityLog::log('forms.delete', 'form_letter', (int) $letter['id'], "حذف خطاب: {$letter['title']}");
        flash_set('success', 'تم حذف الخطاب.');
        redirect('/forms');
    }

    // ---------------------------------------------------------------

    private function companyName(int $companyId): string
    {
        $c = Database::first('SELECT name FROM companies WHERE id = :id', ['id' => $companyId]);
        return $c['name'] ?? '';
    }

    /** خيارات الموظفين للاختيار (فارغة إن كانت إضافة الملف الوظيفي غير مفعّلة). */
    private function employeeOptions(int $companyId): array
    {
        if (!ModuleManager::isActive('employees')) {
            return [];
        }
        return Database::select(
            "SELECT id, full_name, job_title FROM employees_profiles
              WHERE company_id = :c AND status != 'terminated' ORDER BY full_name",
            ['c' => $companyId]
        );
    }

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('forms::no-company', ['pageTitle' => 'النماذج']);
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
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('forms.manage');
    }

    private function findVisible(int $id, int $companyId): array
    {
        $letter = FormLetter::find($id);
        if (!$letter || (int) $letter['company_id'] !== $companyId) {
            flash_set('error', 'الخطاب غير موجود.');
            redirect('/forms');
        }
        return $letter;
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

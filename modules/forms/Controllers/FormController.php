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
            'mySignatures' => \App\Core\UserSignature::forUser(Auth::id()),
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

        // توقيع المُصدِر: من مكتبة تواقيعه الشخصية فقط (لقطة على الخطاب)
        $signatureFile = null;
        $sigId = (int) Request::input('signature_id', 0);
        if ($sigId) {
            $sig = \App\Core\UserSignature::findForUser($sigId, Auth::id());
            if ($sig) {
                $signatureFile = $sig['image'];
            }
        }

        $number = FormLetter::nextNumber($companyId);
        $letterId = FormLetter::create([
            'company_id' => $companyId,
            'template_id' => $templateId,
            'title' => $template['name'],
            'number' => $number,
            'employee_id' => $employeeId,
            'recipient_name' => $recipient ? mb_substr($recipient, 0, 180) : null,
            'body' => $finalBody,
            'signature_file' => $signatureFile,
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

    /** صفحة تحقّق عامة من صحة خطاب عبر رمزه (بلا مصادقة) - تعرض الخطاب ثم بيانات التأكيد. */
    public function verify(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $letter = ctype_xdigit($token) ? FormLetter::findByToken($token) : null;

        View::render('forms::verify', [
            'pageTitle' => 'التحقق من خطاب',
            'letter' => $letter,
            'paperUrl' => $letter ? base_url('forms/verify/' . $token . '/view?embed=1') : null,
        ], '');
    }

    /**
     * عرض ورقة الخطاب نفسها لحامل رمز التحقق (بلا مصادقة): من يمسح الرمز يحمل
     * الورقة أصلاً، فعرضها يتيح المطابقة البصرية مع الأصل. الصور (توقيع/ختم/خلفية)
     * تُضمَّن data URI لأن مسارات /media المحمية لا تعمل لزائر غير مسجّل.
     */
    public function verifyView(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $letter = ctype_xdigit($token) ? FormLetter::findByToken($token) : null;
        if (!$letter) {
            http_response_code(404);
            exit;
        }
        $companyId = (int) $letter['company_id'];
        $settings = FormSetting::getOrCreate($companyId);

        $signatureUrl = null;
        if (!empty($letter['signature_file'])) {
            $signatureUrl = \App\Core\Uploads::dataUri(BASE_PATH . '/storage/uploads/signatures/' . $companyId . '/' . $letter['signature_file']);
        } elseif (!empty($settings['signature_image'])) {
            $signatureUrl = \App\Core\Uploads::dataUri(BASE_PATH . '/storage/uploads/forms/' . $companyId . '/' . $settings['signature_image']);
        }

        $stampUrl = null;
        $template = !empty($letter['template_id']) ? FormTemplate::find((int) $letter['template_id']) : null;
        if ($template && (int) $template['company_id'] !== $companyId) {
            $template = null;
        }
        if ($template && !empty($template['stamp_id'])) {
            $stamp = \App\Core\CompanyStamp::findForCompany((int) $template['stamp_id'], $companyId);
            if ($stamp) {
                $stampUrl = \App\Core\Uploads::dataUri(BASE_PATH . '/storage/uploads/stamps/' . $companyId . '/' . $stamp['image']);
            }
        }
        if (!$stampUrl && !empty($settings['stamp_image'])) {
            $stampUrl = \App\Core\Uploads::dataUri(BASE_PATH . '/storage/uploads/forms/' . $companyId . '/' . $settings['stamp_image']);
        }

        $signerName = $settings['signer_name'] ?? null;
        if (!empty($letter['created_by'])) {
            $issuer = Database::first('SELECT name FROM users WHERE id = :id', ['id' => $letter['created_by']]);
            if ($issuer) {
                $signerName = $issuer['name'];
            }
        }

        View::render('forms::print', [
            'pageTitle' => $letter['title'],
            'letter' => $letter,
            'settings' => $settings,
            'signatureUrl' => $signatureUrl,
            'stampUrl' => $stampUrl,
            'signerName' => $signerName,
            'template' => $template,
            'verifyUrl' => base_url('forms/verify/' . $token),
            'bgUrl' => !empty($settings['background_image'])
                ? \App\Core\Uploads::dataUri(BASE_PATH . '/storage/uploads/forms/' . $companyId . '/' . $settings['background_image'])
                : null,
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

        // التوقيع: توقيع المُصدِر الشخصي المختار (لقطة)، وإلا توقيع الشركة القديم.
        $signatureUrl = null;
        if (!empty($letter['signature_file'])) {
            $signatureUrl = route('/media/signatures/' . $companyId . '/' . $letter['signature_file']);
        } elseif (!empty($settings['signature_image'])) {
            $signatureUrl = route('/media/forms/' . $companyId . '/' . $settings['signature_image']);
        }

        // الختم: ختم قالب الخطاب من مكتبة الأختام، وإلا ختم الشركة القديم.
        $stampUrl = null;
        $template = !empty($letter['template_id']) ? FormTemplate::find((int) $letter['template_id']) : null;
        if ($template && (int) $template['company_id'] === $companyId && !empty($template['stamp_id'])) {
            $stamp = \App\Core\CompanyStamp::findForCompany((int) $template['stamp_id'], $companyId);
            if ($stamp) {
                $stampUrl = \App\Core\CompanyStamp::imageUrl($stamp);
            }
        }
        if (!$stampUrl && !empty($settings['stamp_image'])) {
            $stampUrl = route('/media/forms/' . $companyId . '/' . $settings['stamp_image']);
        }

        // اسم المُصدِر: من أصدر الخطاب فعلاً، وإلا الاسم المعرّف بالإعدادات.
        $signerName = $settings['signer_name'] ?? null;
        if (!empty($letter['created_by'])) {
            $issuer = \App\Core\Database::first('SELECT name FROM users WHERE id = :id', ['id' => $letter['created_by']]);
            if ($issuer) {
                $signerName = $issuer['name'];
            }
        }

        View::render('forms::print', [
            'pageTitle' => $letter['title'],
            'letter' => $letter,
            'settings' => $settings,
            'signatureUrl' => $signatureUrl,
            'stampUrl' => $stampUrl,
            'signerName' => $signerName,
            'template' => $template,
            'verifyUrl' => !empty($letter['verify_token']) ? base_url('forms/verify/' . $letter['verify_token']) : null,
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

    // ---------------- طلبات الخطابات (الخدمة الذاتية) ----------------

    /** قائمة الطلبات: المدير يرى الكل (المعلّقة أولاً)، والموظف طلباته فقط. */
    public function requests(): void
    {
        $companyId = $this->requireCompanyContext();
        $own = $this->ownEmployee($companyId);
        $canManage = $this->canManage();
        if (!$canManage && !$own) {
            $this->forbidden();
            return;
        }

        $where = $canManage ? 'r.company_id = :c' : 'r.company_id = :c AND r.employee_id = ' . (int) $own['id'];
        View::render('forms::requests', [
            'pageTitle' => 'طلبات الخطابات',
            'requests' => Database::select(
                "SELECT r.*, e.full_name, t.name AS template_name, u.name AS decided_by_name
                   FROM forms_requests r
                   JOIN employees_profiles e ON e.id = r.employee_id
                   JOIN forms_templates t ON t.id = r.template_id
                   LEFT JOIN users u ON u.id = r.decided_by
                  WHERE {$where}
                  ORDER BY (r.status = 'pending') DESC, r.id DESC LIMIT 200",
                ['c' => $companyId]
            ),
            'own' => $own,
            'canManage' => $canManage,
        ]);
    }

    /** نموذج طلب خطاب (للموظف المربوط بملف وظيفي). */
    public function requestForm(): void
    {
        $companyId = $this->requireCompanyContext();
        $own = $this->ownEmployee($companyId);
        if (!$own) {
            flash_set('error', 'حسابك غير مربوط بملف وظيفي — اطلب من الإدارة ربطه أولاً.');
            redirect('/forms/requests');
        }
        View::render('forms::request_form', [
            'pageTitle' => 'طلب خطاب',
            'own' => $own,
            'templates' => FormTemplate::forCompany($companyId, true),
        ]);
    }

    public function storeRequest(): void
    {
        $companyId = $this->requireCompanyContext();
        $own = $this->ownEmployee($companyId);
        if (!$own) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/forms/requests/new');

        $template = FormTemplate::find((int) Request::input('template_id', 0));
        if (!$template || (int) $template['company_id'] !== $companyId || !$template['is_active']) {
            flash_set('error', 'اختر قالباً صحيحاً.');
            redirect('/forms/requests/new');
        }

        $requestId = Database::insert('forms_requests', [
            'company_id' => $companyId,
            'employee_id' => (int) $own['id'],
            'template_id' => (int) $template['id'],
            'note' => mb_substr(trim((string) Request::input('note', '')), 0, 500) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // تنبيه مدراء الشركة ومديري النماذج
        foreach (Database::select(
            "SELECT id FROM users WHERE company_id = :c AND membership_type = 'company_admin' AND status = 'active' AND id != :me",
            ['c' => $companyId, 'me' => Auth::id()]
        ) as $admin) {
            \App\Core\Notification::send((int) $admin['id'], '📨 طلب خطاب جديد', $own['full_name'] . ' يطلب: ' . $template['name'], route('/forms/requests'));
        }

        ActivityLog::log('forms.request', 'form_request', $requestId, "طلب خطاب: {$template['name']} - {$own['full_name']}");
        flash_set('success', 'أُرسل طلبك — سيصلك إشعار عند إصدار الخطاب.');
        redirect('/forms/requests');
    }

    /** اعتماد الطلب: يولّد الخطاب تلقائياً بالحقول المعروفة ويُشعر الموظف. */
    public function approveRequest(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/forms/requests');

        $req = Database::first('SELECT * FROM forms_requests WHERE id = :id AND company_id = :c', ['id' => (int) $params['id'], 'c' => $companyId]);
        if (!$req || $req['status'] !== 'pending') {
            flash_set('error', 'الطلب غير موجود أو سبق البتّ فيه.');
            redirect('/forms/requests');
        }
        $template = FormTemplate::find((int) $req['template_id']);
        $employee = Database::first('SELECT * FROM employees_profiles WHERE id = :id', ['id' => (int) $req['employee_id']]);
        if (!$template || !$employee) {
            flash_set('error', 'القالب أو الموظف لم يعد موجوداً.');
            redirect('/forms/requests');
        }

        // توليد بالحقول المعروفة تلقائياً (حقول يدوية غير معروفة تبقى كما هي بالنص)
        $values = MergeFields::knownValues($companyId, (int) $employee['id'], $this->companyName($companyId));
        $finalBody = MergeFields::render($template['body'], $values);
        $number = FormLetter::nextNumber($companyId);
        $letterId = FormLetter::create([
            'company_id' => $companyId,
            'template_id' => (int) $template['id'],
            'title' => $template['name'],
            'number' => $number,
            'employee_id' => (int) $employee['id'],
            'recipient_name' => mb_substr($employee['full_name'], 0, 180),
            'body' => $finalBody,
            'verify_token' => bin2hex(random_bytes(16)),
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Database::update('forms_requests', [
            'status' => 'done',
            'letter_id' => $letterId,
            'decided_by' => Auth::id(),
            'decided_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $req['id']]);

        if (ModuleManager::isActive('employees') && !empty($employee['linked_user_id'])) {
            \App\Core\Notification::send((int) $employee['linked_user_id'], '📄 صدر خطابك', $template['name'] . ' (رقم ' . $number . ')', route('/forms/' . $letterId));
        }

        ActivityLog::log('forms.request_approve', 'form_letter', $letterId, "إصدار خطاب بطلب ذاتي: {$template['name']} - {$employee['full_name']}");
        flash_set('success', 'صدر الخطاب برقم ' . $number . ' وأُشعر الموظف.');
        redirect('/forms/' . $letterId);
    }

    public function rejectRequest(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/forms/requests');

        $req = Database::first(
            'SELECT r.*, e.linked_user_id, t.name AS template_name FROM forms_requests r
               JOIN employees_profiles e ON e.id = r.employee_id
               JOIN forms_templates t ON t.id = r.template_id
              WHERE r.id = :id AND r.company_id = :c',
            ['id' => (int) $params['id'], 'c' => $companyId]
        );
        if (!$req || $req['status'] !== 'pending') {
            flash_set('error', 'الطلب غير موجود أو سبق البتّ فيه.');
            redirect('/forms/requests');
        }
        $note = mb_substr(trim((string) Request::input('note', '')), 0, 255) ?: null;
        Database::update('forms_requests', [
            'status' => 'rejected',
            'decided_by' => Auth::id(),
            'decided_at' => date('Y-m-d H:i:s'),
            'decision_note' => $note,
        ], 'id = :id', ['id' => $req['id']]);
        if (!empty($req['linked_user_id'])) {
            \App\Core\Notification::send((int) $req['linked_user_id'], '❌ رُفض طلب الخطاب', $req['template_name'] . ($note ? ' — ' . $note : ''), route('/forms/requests'));
        }
        flash_set('success', 'رُفض الطلب.');
        redirect('/forms/requests');
    }

    /** إرسال الخطاب بالبريد للموظف المعني (بريده الشخصي أو بريد حسابه). */
    public function emailLetter(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('forms.generate') && !$this->canManage()) {
            $this->forbidden();
            return;
        }
        $letter = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/forms/' . $letter['id']);

        // بريد المستلم: الشخصي من الملف الوظيفي، وإلا بريد حسابه المربوط
        $email = null;
        if (!empty($letter['employee_id'])) {
            $emp = Database::first('SELECT personal_email, linked_user_id FROM employees_profiles WHERE id = :id', ['id' => $letter['employee_id']]);
            $email = $emp['personal_email'] ?? null;
            if (!$email && !empty($emp['linked_user_id'])) {
                $email = Database::first('SELECT email FROM users WHERE id = :id', ['id' => $emp['linked_user_id']])['email'] ?? null;
            }
        }
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'لا يوجد بريد صالح للموظف — أضف بريده الشخصي في ملفه الوظيفي.');
            redirect('/forms/' . $letter['id']);
        }

        $verifyUrl = base_url('forms/verify/' . $letter['verify_token']);
        $subject = '=?UTF-8?B?' . base64_encode($letter['title'] . ' - ' . ($letter['number'] ?? '')) . '?=';
        $html = '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;line-height:2;max-width:640px;">'
            . '<h3>' . e($letter['title']) . ($letter['number'] ? ' — رقم ' . e($letter['number']) : '') . '</h3>'
            . '<div style="white-space:pre-wrap;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">' . e($letter['body']) . '</div>'
            . '<p style="color:#6b7280;font-size:12px;">للتحقق من صحة هذا الخطاب: <a href="' . e($verifyUrl) . '">' . e($verifyUrl) . '</a></p>'
            . '</div>';
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";

        if (@mail($email, $subject, $html, $headers)) {
            ActivityLog::log('forms.email', 'form_letter', (int) $letter['id'], "إرسال خطاب بالبريد إلى {$email}");
            flash_set('success', 'أُرسل الخطاب إلى ' . $email . '.');
        } else {
            flash_set('error', 'تعذّر الإرسال — تأكد أن الاستضافة تدعم إرسال البريد.');
        }
        redirect('/forms/' . $letter['id']);
    }

    /** ملف الموظف المربوط بحساب المستخدم الحالي (للخدمة الذاتية). */
    private function ownEmployee(int $companyId): ?array
    {
        if (!ModuleManager::isActive('employees')) {
            return null;
        }
        try {
            return Database::first('SELECT * FROM employees_profiles WHERE company_id = :c AND linked_user_id = :u', ['c' => $companyId, 'u' => Auth::id()]);
        } catch (\Throwable $e) {
            return null;
        }
    }

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

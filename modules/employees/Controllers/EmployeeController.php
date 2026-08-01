<?php

namespace Modules\Employees\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use Modules\Employees\Models\Employee;
use Modules\Employees\Models\EmployeeCertification;
use Modules\Employees\Models\EmployeeDependent;
use Modules\Employees\Models\EmployeeDocument;
use Modules\Employees\Models\EmployeeTimeline;

class EmployeeController
{
    private const STATUSES = ['active', 'on_leave', 'suspended', 'terminated'];
    private const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract'];
    private const GENDERS = ['male', 'female'];
    private const MARITAL_STATUSES = ['single', 'married', 'divorced', 'widowed'];
    private const DEPENDENT_RELATIONS = ['spouse', 'child', 'other'];
    private const DOC_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];
    private const DOC_MAX_BYTES = 10 * 1024 * 1024;

    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('employees.view')) {
            $this->forbidden();
            return;
        }

        $filters = $this->currentFilters();
        $page = max(1, (int) Request::query('page', 1));
        $result = Employee::paginate($companyId, $filters, $page, 20);

        View::render('employees::index', [
            'pageTitle' => 'الملف الوظيفي',
            'employees' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'filters' => $filters,
            'canCreate' => $this->can('employees.create'),
            'canViewSensitive' => $this->canViewSensitive(),
            'expiringCount' => $this->canViewSensitive() ? Employee::countExpiringDocuments($companyId, 60) : 0,
        ]);
    }

    /** تنبيهات الوثائق: كل ما انتهى أو سينتهي قريباً لموظفي الشركة، مرتّباً بالأولوية. */
    public function expiring(): void
    {
        $companyId = $this->requireCompanyContext();
        // بيانات حسّاسة (أرقام/تواريخ إقامة وجوازات) - نفس بوابة البيانات الحساسة
        if (!$this->canViewSensitive()) {
            $this->forbidden();
            return;
        }

        $within = (int) Request::query('within', 60);
        if (!in_array($within, [30, 60, 90, 180], true)) {
            $within = 60;
        }

        View::render('employees::expiring', [
            'pageTitle' => 'تنبيهات الوثائق',
            'within' => $within,
            'documents' => Employee::expiringDocuments($companyId, $within),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('employees.create')) {
            $this->forbidden();
            return;
        }

        View::render('employees::form', [
            'pageTitle' => 'ملف وظيفي جديد',
            'employee' => null,
            'managers' => Employee::selectableManagers($companyId),
            'companyUsers' => $this->linkableUsers($companyId, null),
            'canViewSensitive' => $this->canViewSensitive(),
            'canManage' => $this->canManage(),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('employees.create')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/create');

        $data = $this->validated($companyId, null);
        if ($data === null) {
            redirect('/employees/create');
        }

        $photo = Uploads::handleImage('photo', BASE_PATH . '/storage/uploads/employees/' . $companyId);
        if ($photo['error']) {
            flash_set('error', $photo['error']);
            redirect('/employees/create');
        }
        if ($photo['filename']) {
            $data['photo'] = $photo['filename'];
        }

        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();

        $employeeId = Employee::create($data);
        EmployeeTimeline::add($employeeId, 'hire', 'إنشاء الملف الوظيفي', $data['hire_date'] ?? date('Y-m-d'), Auth::id());

        ActivityLog::log('employees.create', 'employee', $employeeId, "إضافة ملف وظيفي: {$data['full_name']}");
        flash_set('success', 'تم إنشاء الملف الوظيفي.');
        redirect('/employees/' . $employeeId);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);

        View::render('employees::show', [
            'pageTitle' => $employee['full_name'],
            'employee' => $employee,
            'manager' => $employee['manager_employee_id'] ? Employee::find((int) $employee['manager_employee_id']) : null,
            'dependents' => EmployeeDependent::forEmployee($employee['id']),
            'certifications' => EmployeeCertification::forEmployee($employee['id']),
            'documents' => EmployeeDocument::forEmployee($employee['id']),
            'timeline' => EmployeeTimeline::forEmployee($employee['id']),
            'canEdit' => $this->can('employees.edit'),
            'canDelete' => $this->can('employees.delete'),
            'canViewSensitive' => $this->canViewSensitive(),
            'canManage' => $this->canManage(),
        ]);
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('employees.edit')) {
            $this->forbidden();
            return;
        }

        View::render('employees::form', [
            'pageTitle' => 'تعديل: ' . $employee['full_name'],
            'employee' => $employee,
            'managers' => Employee::selectableManagers($companyId, $employee['id']),
            'companyUsers' => $this->linkableUsers($companyId, (int) $employee['id']),
            'canViewSensitive' => $this->canViewSensitive(),
            'canManage' => $this->canManage(),
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('employees.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/' . $employee['id'] . '/edit');

        $data = $this->validated($companyId, $employee['id']);
        if ($data === null) {
            redirect('/employees/' . $employee['id'] . '/edit');
        }

        $photo = Uploads::handleImage('photo', BASE_PATH . '/storage/uploads/employees/' . $companyId);
        if ($photo['error']) {
            flash_set('error', $photo['error']);
            redirect('/employees/' . $employee['id'] . '/edit');
        }
        if ($photo['filename']) {
            $data['photo'] = $photo['filename'];
        }

        $oldStatus = $employee['status'];
        Employee::update($employee['id'], $data);

        if ($data['status'] !== $oldStatus) {
            $labels = Employee::statusLabels();
            EmployeeTimeline::add(
                $employee['id'],
                'status_change',
                'تغيير الحالة من "' . ($labels[$oldStatus] ?? $oldStatus) . '" إلى "' . ($labels[$data['status']] ?? $data['status']) . '"',
                date('Y-m-d'),
                Auth::id()
            );
        }

        ActivityLog::log('employees.update', 'employee', $employee['id'], "تعديل ملف وظيفي: {$data['full_name']}");
        flash_set('success', 'تم حفظ التعديلات.');
        redirect('/employees/' . $employee['id']);
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('employees.delete')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/' . $employee['id']);

        foreach (EmployeeDocument::forEmployee($employee['id']) as $doc) {
            $path = BASE_PATH . '/storage/uploads/employees/' . $companyId . '/' . $doc['stored_name'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        if ($employee['photo']) {
            $path = BASE_PATH . '/storage/uploads/employees/' . $companyId . '/' . $employee['photo'];
            if (is_file($path)) {
                @unlink($path);
            }
        }

        Employee::delete($employee['id']);
        ActivityLog::log('employees.delete', 'employee', $employee['id'], "حذف ملف وظيفي: {$employee['full_name']}");
        flash_set('success', 'تم حذف الملف الوظيفي.');
        redirect('/employees');
    }

    // ---------------------------------------------------------------
    // مُعالون

    public function addDependent(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('employees.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/' . $employee['id']);

        $name = trim((string) Request::input('name', ''));
        $relation = Request::input('relation', 'child');
        if (!in_array($relation, self::DEPENDENT_RELATIONS, true)) {
            $relation = 'child';
        }
        $birthDate = Request::input('birth_date') ?: null;

        if ($name === '') {
            flash_set('error', 'اسم المُعال مطلوب.');
            redirect('/employees/' . $employee['id']);
        }

        EmployeeDependent::create([
            'employee_id' => $employee['id'],
            'name' => $name,
            'relation' => $relation,
            'birth_date' => $birthDate,
        ]);

        flash_set('success', 'تمت إضافة المُعال.');
        redirect('/employees/' . $employee['id']);
    }

    public function deleteDependent(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('employees.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/' . $employee['id']);

        $dependent = EmployeeDependent::find((int) $params['dependentId']);
        if ($dependent && (int) $dependent['employee_id'] === $employee['id']) {
            EmployeeDependent::delete($dependent['id']);
            flash_set('success', 'تم حذف المُعال.');
        }
        redirect('/employees/' . $employee['id']);
    }

    // ---------------------------------------------------------------
    // الدورات والشهادات

    public function addCertification(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('employees.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/' . $employee['id']);

        $title = trim((string) Request::input('title', ''));
        if ($title === '') {
            flash_set('error', 'عنوان الدورة/الشهادة مطلوب.');
            redirect('/employees/' . $employee['id']);
        }

        EmployeeCertification::create([
            'employee_id' => $employee['id'],
            'title' => $title,
            'issuer' => trim((string) Request::input('issuer', '')) ?: null,
            'issue_date' => Request::input('issue_date') ?: null,
            'expiry_date' => Request::input('expiry_date') ?: null,
        ]);

        flash_set('success', 'تمت إضافة الدورة/الشهادة.');
        redirect('/employees/' . $employee['id']);
    }

    public function deleteCertification(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('employees.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/' . $employee['id']);

        $cert = EmployeeCertification::find((int) $params['certId']);
        if ($cert && (int) $cert['employee_id'] === $employee['id']) {
            EmployeeCertification::delete($cert['id']);
            flash_set('success', 'تم حذف السجل.');
        }
        redirect('/employees/' . $employee['id']);
    }

    // ---------------------------------------------------------------
    // المستندات

    public function uploadDocument(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('employees.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/' . $employee['id']);

        $title = trim((string) Request::input('title', ''));
        $upload = Uploads::handleFile('file', BASE_PATH . '/storage/uploads/employees/' . $companyId, self::DOC_EXTENSIONS, self::DOC_MAX_BYTES);

        if ($upload['error']) {
            flash_set('error', $upload['error']);
            redirect('/employees/' . $employee['id']);
        }
        if (!$upload['filename']) {
            flash_set('error', 'يرجى اختيار ملف لرفعه.');
            redirect('/employees/' . $employee['id']);
        }

        EmployeeDocument::create([
            'employee_id' => $employee['id'],
            'title' => $title !== '' ? $title : $upload['original'],
            'original_name' => $upload['original'],
            'stored_name' => $upload['filename'],
            'extension' => $upload['extension'],
            'size' => $upload['size'],
            'uploaded_by' => Auth::id(),
        ]);

        flash_set('success', 'تم رفع المستند.');
        redirect('/employees/' . $employee['id']);
    }

    public function downloadDocument(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);

        $doc = EmployeeDocument::find((int) $params['docId']);
        if (!$doc || (int) $doc['employee_id'] !== $employee['id']) {
            http_response_code(404);
            exit;
        }

        $path = BASE_PATH . '/storage/uploads/employees/' . $companyId . '/' . $doc['stored_name'];
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($doc['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function deleteDocument(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('employees.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/' . $employee['id']);

        $doc = EmployeeDocument::find((int) $params['docId']);
        if ($doc && (int) $doc['employee_id'] === $employee['id']) {
            $path = BASE_PATH . '/storage/uploads/employees/' . $companyId . '/' . $doc['stored_name'];
            if (is_file($path)) {
                @unlink($path);
            }
            EmployeeDocument::delete($doc['id']);
            flash_set('success', 'تم حذف المستند.');
        }
        redirect('/employees/' . $employee['id']);
    }

    // ---------------------------------------------------------------
    // السجل الوظيفي (Timeline)

    public function addTimelineEntry(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('employees.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/' . $employee['id']);

        $description = trim((string) Request::input('description', ''));
        if ($description === '') {
            flash_set('error', 'لا يمكن إضافة سجل فارغ.');
            redirect('/employees/' . $employee['id']);
        }

        $eventType = Request::input('event_type', 'note');
        if (!array_key_exists($eventType, Employee::timelineEventLabels())) {
            $eventType = 'note';
        }
        $eventDate = Request::input('event_date') ?: date('Y-m-d');

        EmployeeTimeline::add($employee['id'], $eventType, $description, $eventDate, Auth::id());
        flash_set('success', 'تمت إضافة السجل.');
        redirect('/employees/' . $employee['id']);
    }

    public function deleteTimelineEntry(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $employee = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/employees/' . $employee['id']);

        $entry = EmployeeTimeline::find((int) $params['entryId']);
        if ($entry && (int) $entry['employee_id'] === $employee['id']) {
            EmployeeTimeline::delete($entry['id']);
            flash_set('success', 'تم حذف السجل.');
        }
        redirect('/employees/' . $employee['id']);
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('employees::no-company', ['pageTitle' => 'الملف الوظيفي']);
            exit;
        }
        return $companyId;
    }

    private function can(string $key): bool
    {
        return Permission::check($key) || Permission::check('employees.manage');
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('employees.manage');
    }

    private function canViewSensitive(): bool
    {
        return $this->canManage() || Permission::check('employees.view_sensitive');
    }

    /** يجد الملف مع مراعاة استثناء "شاهد ملفك الخاص": موظف مرتبط حسابه بهذا الملف يراه دون أي صلاحية، حتى لو لم يُمنح employees.view. */
    private function findVisible(int $id, int $companyId): array
    {
        $employee = Employee::find($id);
        if (!$employee || (int) $employee['company_id'] !== $companyId) {
            flash_set('error', 'الملف الوظيفي غير موجود.');
            redirect('/employees');
        }

        $isOwnProfile = $employee['linked_user_id'] && (int) $employee['linked_user_id'] === Auth::id();
        if (!$isOwnProfile && !$this->can('employees.view')) {
            $this->forbidden();
            exit;
        }

        return $employee;
    }

    /** مستخدمو الشركة الذين لا يملكون ملفاً وظيفياً بعد (+ المرتبط حالياً بهذا الملف عند التعديل، ليبقى مختاراً). */
    private function linkableUsers(int $companyId, ?int $currentEmployeeId): array
    {
        $linkedIds = array_column(
            Database::select(
                'SELECT linked_user_id FROM employees_profiles WHERE company_id = :c AND linked_user_id IS NOT NULL' . ($currentEmployeeId ? ' AND id != :ex' : ''),
                $currentEmployeeId ? ['c' => $companyId, 'ex' => $currentEmployeeId] : ['c' => $companyId]
            ),
            'linked_user_id'
        );

        $users = Database::select('SELECT id, name, email FROM users WHERE company_id = :c AND status = "active" ORDER BY name', ['c' => $companyId]);
        return array_values(array_filter($users, fn ($u) => !in_array((int) $u['id'], array_map('intval', $linkedIds), true)));
    }

    /** تصدير قائمة الموظفين (بنفس فلاتر الصفحة) - بلا بيانات حساسة. */
    public function export(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('employees.view')) {
            $this->forbidden();
            return;
        }
        $filters = $this->currentFilters();
        $employees = Employee::paginate($companyId, $filters, 1, 100000)['rows'];

        $statusLabels = ['active' => 'نشط', 'on_leave' => 'إجازة', 'suspended' => 'موقوف', 'terminated' => 'منتهي الخدمة'];
        $headers = ['الاسم', 'المسمى الوظيفي', 'القسم', 'الفرع', 'الجوال', 'البريد', 'تاريخ التعيين', 'الحالة'];
        $rows = array_map(fn ($e) => [
            $e['full_name'],
            $e['job_title'] ?? '-',
            $e['department'] ?? '-',
            $e['branch'] ?? '-',
            $e['phone'] ?? '-',
            $e['personal_email'] ?? '-',
            $e['hire_date'] ? format_date($e['hire_date']) : '-',
            $statusLabels[$e['status']] ?? $e['status'],
        ], $employees);

        if (($params['format'] ?? 'csv') === 'print') {
            \App\Core\Export::printable('الموظفون', $headers, $rows);
        }
        \App\Core\Export::csv('employees', $headers, $rows);
    }

    private function currentFilters(): array
    {
        $filters = [];
        $status = Request::query('status', '');
        if (in_array($status, self::STATUSES, true)) {
            $filters['status'] = $status;
        }
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        return $filters;
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

    private function validated(int $companyId, ?int $currentId): ?array
    {
        $fullName = trim((string) Request::input('full_name', ''));
        if ($fullName === '') {
            flash_set('error', 'اسم الموظف مطلوب.');
            return null;
        }

        $status = Request::input('status', 'active');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'active';
        }
        $employmentType = Request::input('employment_type', 'full_time');
        if (!in_array($employmentType, self::EMPLOYMENT_TYPES, true)) {
            $employmentType = 'full_time';
        }
        $gender = Request::input('gender') ?: null;
        if ($gender !== null && !in_array($gender, self::GENDERS, true)) {
            $gender = null;
        }
        $maritalStatus = Request::input('marital_status') ?: null;
        if ($maritalStatus !== null && !in_array($maritalStatus, self::MARITAL_STATUSES, true)) {
            $maritalStatus = null;
        }

        $linkedUserId = (int) Request::input('linked_user_id', 0) ?: null;
        if ($linkedUserId) {
            $user = Database::first('SELECT id FROM users WHERE id = :id AND company_id = :c', ['id' => $linkedUserId, 'c' => $companyId]);
            if (!$user) {
                $linkedUserId = null;
            } else {
                $existing = Employee::findByLinkedUser($companyId, $linkedUserId);
                if ($existing && (!$currentId || (int) $existing['id'] !== $currentId)) {
                    flash_set('error', 'هذا الحساب مرتبط بملف وظيفي آخر بالفعل.');
                    return null;
                }
            }
        }

        $managerEmployeeId = (int) Request::input('manager_employee_id', 0) ?: null;
        if ($managerEmployeeId && $currentId && $managerEmployeeId === $currentId) {
            $managerEmployeeId = null;
        }
        // المدير المباشر يجب أن يكون ملفاً وظيفياً في نفس الشركة (منع تمرير معرّف
        // من شركة أخرى مباشرة يتجاوز قائمة الاختيار المقيّدة بالشركة).
        if ($managerEmployeeId) {
            $mgr = Employee::find($managerEmployeeId);
            if (!$mgr || (int) $mgr['company_id'] !== (int) $companyId) {
                $managerEmployeeId = null;
            }
        }

        $data = [
            'full_name' => $fullName,
            'linked_user_id' => $linkedUserId,
            'national_id' => trim((string) Request::input('national_id', '')) ?: null,
            'nationality' => trim((string) Request::input('nationality', '')) ?: null,
            'birth_date' => Request::input('birth_date') ?: null,
            'gender' => $gender,
            'marital_status' => $maritalStatus,
            'phone' => trim((string) Request::input('phone', '')) ?: null,
            'personal_email' => trim((string) Request::input('personal_email', '')) ?: null,
            'address' => trim((string) Request::input('address', '')) ?: null,
            'emergency_contact_name' => trim((string) Request::input('emergency_contact_name', '')) ?: null,
            'emergency_contact_phone' => trim((string) Request::input('emergency_contact_phone', '')) ?: null,
            'emergency_contact_relation' => trim((string) Request::input('emergency_contact_relation', '')) ?: null,
            'job_title' => trim((string) Request::input('job_title', '')) ?: null,
            'department' => trim((string) Request::input('department', '')) ?: null,
            'manager_employee_id' => $managerEmployeeId,
            'branch' => trim((string) Request::input('branch', '')) ?: null,
            'hire_date' => Request::input('hire_date') ?: null,
            'employment_type' => $employmentType,
            'status' => $status,
            'skills' => trim((string) Request::input('skills', '')) ?: null,
            'languages' => trim((string) Request::input('languages', '')) ?: null,
        ];

        if ($this->canViewSensitive()) {
            $data = array_merge($data, [
                'contract_type' => trim((string) Request::input('contract_type', '')) ?: null,
                'contract_start_date' => Request::input('contract_start_date') ?: null,
                'contract_end_date' => Request::input('contract_end_date') ?: null,
                'probation_end_date' => Request::input('probation_end_date') ?: null,
                'salary_base' => Request::input('salary_base') !== null && Request::input('salary_base') !== '' ? (float) Request::input('salary_base') : null,
                'salary_allowances' => Request::input('salary_allowances') !== null && Request::input('salary_allowances') !== '' ? (float) Request::input('salary_allowances') : null,
                'bank_name' => trim((string) Request::input('bank_name', '')) ?: null,
                'bank_iban' => trim((string) Request::input('bank_iban', '')) ?: null,
                'gosi_number' => trim((string) Request::input('gosi_number', '')) ?: null,
                'medical_insurance_provider' => trim((string) Request::input('medical_insurance_provider', '')) ?: null,
                'medical_insurance_policy_number' => trim((string) Request::input('medical_insurance_policy_number', '')) ?: null,
                'driving_license_number' => trim((string) Request::input('driving_license_number', '')) ?: null,
                'driving_license_expiry' => Request::input('driving_license_expiry') ?: null,
                'passport_number' => trim((string) Request::input('passport_number', '')) ?: null,
                'passport_expiry' => Request::input('passport_expiry') ?: null,
                'iqama_expiry' => Request::input('iqama_expiry') ?: null,
                'health_cert_expiry' => Request::input('health_cert_expiry') ?: null,
                'insurance_expiry' => Request::input('insurance_expiry') ?: null,
            ]);
        }

        if ($this->canManage()) {
            $data['admin_notes'] = trim((string) Request::input('admin_notes', '')) ?: null;
        }

        return $data;
    }
}

<?php

namespace Modules\Forms\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;
use Modules\Forms\Models\FormTemplate;
use Modules\Forms\Models\MergeFields;

class FormTemplateController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        View::render('forms::templates.index', [
            'pageTitle' => 'قوالب النماذج',
            'templates' => FormTemplate::forCompany($companyId),
        ]);
    }

    public function create(): void
    {
        $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        View::render('forms::templates.form', [
            'pageTitle' => 'قالب جديد',
            'template' => null,
            'knownFields' => MergeFields::knownFields(),
            'stamps' => \App\Core\CompanyStamp::usableBy((int) \App\Core\Auth::id(), (int) \App\Core\Auth::companyId(), true),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/forms/templates/create');
        $data = $this->validated();
        if ($data === null) {
            redirect('/forms/templates/create');
        }
        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = FormTemplate::create($data);
        ActivityLog::log('forms.template_create', 'form_template', $id, "إضافة قالب نموذج: {$data['name']}");
        flash_set('success', 'تمت إضافة القالب.');
        redirect('/forms/templates');
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $template = $this->findVisible((int) $params['id'], $companyId);
        View::render('forms::templates.form', [
            'pageTitle' => 'تعديل: ' . $template['name'],
            'template' => $template,
            'knownFields' => MergeFields::knownFields(),
            'stamps' => \App\Core\CompanyStamp::usableBy((int) \App\Core\Auth::id(), (int) \App\Core\Auth::companyId(), true),
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $template = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/forms/templates/' . $template['id'] . '/edit');
        $data = $this->validated();
        if ($data === null) {
            redirect('/forms/templates/' . $template['id'] . '/edit');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        FormTemplate::update((int) $template['id'], $data);
        ActivityLog::log('forms.template_update', 'form_template', (int) $template['id'], "تعديل قالب نموذج: {$data['name']}");
        flash_set('success', 'تم حفظ القالب.');
        redirect('/forms/templates');
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $template = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/forms/templates');
        FormTemplate::delete((int) $template['id']);
        ActivityLog::log('forms.template_delete', 'form_template', (int) $template['id'], "حذف قالب نموذج: {$template['name']}");
        flash_set('success', 'تم حذف القالب (الخطابات المولّدة سابقاً تبقى كما هي).');
        redirect('/forms/templates');
    }

    // ---------------------------------------------------------------

    private function validated(): ?array
    {
        $name = trim((string) Request::input('name', ''));
        $body = trim((string) Request::input('body', ''));
        if ($name === '' || $body === '') {
            flash_set('error', 'اسم القالب ونصه مطلوبان.');
            return null;
        }
        // ختم القالب: يجب أن يخصّ الشركة (وإلا يُهمَل) - null يعني بلا ختم
        $stampId = (int) Request::input('stamp_id', 0) ?: null;
        if ($stampId && !\App\Core\CompanyStamp::findUsableBy($stampId, (int) \App\Core\Auth::id(), (int) \App\Core\Auth::companyId(), true)) {
            $stampId = null;
        }
        $qrColor = (string) Request::input('qr_color', '#000000');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $qrColor)) {
            $qrColor = '#000000';
        }
        return [
            'name' => mb_substr($name, 0, 160),
            'body' => $body,
            'is_active' => Request::input('is_active') ? 1 : 0,
            'stamp_id' => $stampId,
            'qr_enabled' => Request::input('qr_enabled') ? 1 : 0,
            'qr_x' => max(0, min(2000, (int) Request::input('qr_x', 40))),
            'qr_y' => max(0, min(2000, (int) Request::input('qr_y', 40))),
            'qr_size' => max(40, min(400, (int) Request::input('qr_size', 90))),
            'qr_color' => $qrColor,
        ];
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

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('forms.manage');
    }

    private function findVisible(int $id, int $companyId): array
    {
        $t = FormTemplate::find($id);
        if (!$t || (int) $t['company_id'] !== $companyId) {
            flash_set('error', 'القالب غير موجود.');
            redirect('/forms/templates');
        }
        return $t;
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

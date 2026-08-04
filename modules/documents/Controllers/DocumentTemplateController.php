<?php

namespace Modules\Documents\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HtmlSanitizer;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use Modules\Documents\Models\DocumentTemplate;

class DocumentTemplateController
{
    private const POSITIONS = ['top-right', 'top-left', 'bottom-right', 'bottom-left'];

    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardManage();

        View::render('documents::templates.index', [
            'pageTitle' => 'قوالب المستندات',
            'templates' => DocumentTemplate::forCompany($companyId),
        ]);
    }

    public function create(): void
    {
        $this->requireCompanyContext();
        $this->guardManage();

        View::render('documents::templates.form', [
            'pageTitle' => 'قالب جديد',
            'template' => null,
            'positions' => self::POSITIONS,
            'stamps' => \App\Core\CompanyStamp::forCompany((int) \App\Core\Auth::companyId()),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardManage();
        $this->verifyCsrf('/documents/templates/create');

        $data = $this->validated();
        if ($data === null) {
            redirect('/documents/templates/create');
        }
        $data['company_id'] = $companyId;

        $upload = Uploads::handleImage('background_image', BASE_PATH . '/storage/uploads/documents/' . $companyId);
        if ($upload['error']) {
            flash_set('error', $upload['error']);
            redirect('/documents/templates/create');
        }
        if ($upload['filename']) {
            $data['background_image'] = $upload['filename'];
        }

        $templateId = DocumentTemplate::create($data);
        ActivityLog::log('documents.template.create', 'document_template', $templateId, "إنشاء قالب: {$data['name']}");
        flash_set('success', 'تم إنشاء القالب.');
        redirect('/documents/templates');
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardManage();
        $template = $this->findInCompany((int) $params['id'], $companyId);

        View::render('documents::templates.form', [
            'pageTitle' => 'تعديل قالب',
            'template' => $template,
            'positions' => self::POSITIONS,
            'stamps' => \App\Core\CompanyStamp::forCompany((int) \App\Core\Auth::companyId()),
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardManage();
        $template = $this->findInCompany((int) $params['id'], $companyId);
        $this->verifyCsrf('/documents/templates/' . $template['id'] . '/edit');

        $data = $this->validated();
        if ($data === null) {
            redirect('/documents/templates/' . $template['id'] . '/edit');
        }

        $upload = Uploads::handleImage('background_image', BASE_PATH . '/storage/uploads/documents/' . $companyId);
        if ($upload['error']) {
            flash_set('error', $upload['error']);
            redirect('/documents/templates/' . $template['id'] . '/edit');
        }
        if ($upload['filename']) {
            $data['background_image'] = $upload['filename'];
            if ($template['background_image']) {
                @unlink(BASE_PATH . '/storage/uploads/documents/' . $companyId . '/' . $template['background_image']);
            }
        }

        DocumentTemplate::update($template['id'], $data);
        ActivityLog::log('documents.template.update', 'document_template', $template['id'], "تعديل قالب: {$data['name']}");
        flash_set('success', 'تم حفظ التعديلات.');
        redirect('/documents/templates');
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardManage();
        $template = $this->findInCompany((int) $params['id'], $companyId);
        $this->verifyCsrf('/documents/templates');

        // إفراغ ربط القالب من أي مستند يستخدمه (لا يوجد FK عمداً لاستقلال الإضافة عن النواة)
        Database::update('documents_documents', ['template_id' => null], 'template_id = :id', ['id' => $template['id']]);

        if ($template['background_image']) {
            @unlink(BASE_PATH . '/storage/uploads/documents/' . $companyId . '/' . $template['background_image']);
        }

        DocumentTemplate::delete($template['id']);
        ActivityLog::log('documents.template.delete', 'document_template', $template['id'], "حذف قالب: {$template['name']}");
        flash_set('success', 'تم حذف القالب.');
        redirect('/documents/templates');
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('documents::no-company', ['pageTitle' => 'قوالب المستندات']);
            exit;
        }
        return $companyId;
    }

    private function guardManage(): void
    {
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin() && !\App\Core\Permission::check('documents.manage')) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
    }

    private function findInCompany(int $id, int $companyId): array
    {
        $template = DocumentTemplate::find($id);
        if (!$template || (int) $template['company_id'] !== $companyId) {
            flash_set('error', 'القالب غير موجود.');
            redirect('/documents/templates');
        }
        return $template;
    }

    private function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    private function validated(): ?array
    {
        $name = trim((string) Request::input('name', ''));
        $position = Request::input('number_position', 'top-right');
        $showDate = Request::input('show_date') ? 1 : 0;
        $showNumber = Request::input('show_number') ? 1 : 0;
        $headerHtml = HtmlSanitizer::sanitize(Request::input('header_html', ''));
        $footerHtml = HtmlSanitizer::sanitize(Request::input('footer_html', ''));

        if ($name === '') {
            flash_set('error', 'اسم القالب مطلوب.');
            return null;
        }
        if (!in_array($position, self::POSITIONS, true)) {
            $position = 'top-right';
        }

        // ختم القالب: يجب أن يخصّ الشركة (وإلا يُهمَل) - null يعني بلا ختم
        $stampId = (int) Request::input('stamp_id', 0) ?: null;
        if ($stampId && !\App\Core\CompanyStamp::findForCompany($stampId, (int) \App\Core\Auth::companyId())) {
            $stampId = null;
        }

        return array_merge([
            'name' => $name,
            'number_position' => $position,
            'show_date' => $showDate,
            'show_number' => $showNumber,
            'stamp_id' => $stampId,
            'header_html' => $headerHtml ?: null,
            'footer_html' => $footerHtml ?: null,
        ], self::qrFields());
    }

    /** حقول رمز QR للتحقق (مشتركة الشكل بين المستندات والنماذج). */
    public static function qrFields(): array
    {
        $color = (string) Request::input('qr_color', '#000000');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#000000';
        }
        return [
            'qr_enabled' => Request::input('qr_enabled') ? 1 : 0,
            'qr_x' => max(0, min(2000, (int) Request::input('qr_x', 40))),
            'qr_y' => max(0, min(2000, (int) Request::input('qr_y', 40))),
            'qr_size' => max(40, min(400, (int) Request::input('qr_size', 90))),
            'qr_color' => $color,
        ];
    }
}

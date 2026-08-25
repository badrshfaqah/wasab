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
        $this->guardView();

        // المدير يرى كل قوالب الشركة، وغيره يرى قوالبه + المشارَكة معه + العامة
        if ($this->isManager()) {
            $templates = \App\Core\Database::select(
                'SELECT t.*, u.name AS owner_name FROM documents_templates t
                   LEFT JOIN users u ON u.id = t.created_by
                  WHERE t.company_id = :c ORDER BY t.name',
                ['c' => $companyId]
            );
        } else {
            $templates = DocumentTemplate::usableBy(Auth::id(), $companyId);
        }
        foreach ($templates as &$t) {
            $t['shared_with'] = DocumentTemplate::shareUserIds((int) $t['id']);
        }
        unset($t);

        View::render('documents::templates.index', [
            'pageTitle' => 'قوالب المستندات',
            'templates' => $templates,
            'isManager' => $this->isManager(),
            'companyUsers' => \App\Core\Database::select(
                "SELECT id, name FROM users WHERE company_id = :c AND status = 'active' AND id != :me ORDER BY name",
                ['c' => $companyId, 'me' => Auth::id()]
            ),
        ]);
    }

    /** مشاركة قالب مع زملاء محددين ليستخدموه - لصاحب القالب (أو المدير). */
    public function share(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $template = $this->findInCompany((int) $params['id'], $companyId);
        $this->verifyCsrf('/documents/templates');
        if (!$this->canEditTemplate($template)) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }

        $requested = array_map('intval', (array) Request::input('user_ids', []));
        $valid = [];
        if ($requested) {
            $placeholders = implode(',', array_fill(0, count($requested), '?'));
            $rows = \App\Core\Database::select(
                "SELECT id FROM users WHERE company_id = ? AND status = 'active' AND id IN ({$placeholders})",
                array_merge([$companyId], $requested)
            );
            $valid = array_values(array_diff(array_map(fn ($r) => (int) $r['id'], $rows), [(int) ($template['created_by'] ?? 0)]));
        }

        $before = DocumentTemplate::shareUserIds((int) $template['id']);
        DocumentTemplate::setShares((int) $template['id'], $valid);
        foreach (array_diff($valid, $before) as $uid) {
            \App\Core\Notification::send(
                $uid,
                '🎨 شُورك معك قالب',
                Auth::user()['name'] . ' أتاح لك استخدام قالب «' . $template['name'] . '» عند كتابة المستندات.',
                route('/documents/create')
            );
        }
        ActivityLog::log('documents.template.share', 'document_template', $template['id'], 'تحديث مشاركة قالب «' . $template['name'] . '» (' . count($valid) . ' مستخدم)');
        flash_set('success', $valid ? 'حُدّثت مشاركة القالب.' : 'أُلغيت مشاركة القالب.');
        redirect('/documents/templates');
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardView();

        View::render('documents::templates.form', [
            'pageTitle' => 'قالب جديد',
            'template' => null,
            'positions' => self::POSITIONS,
            'isManager' => $this->isManager(),
            'stamps' => \App\Core\CompanyStamp::usableBy(Auth::id(), $companyId, $this->isManager()),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardView();
        $this->verifyCsrf('/documents/templates/create');

        $data = $this->validated();
        if ($data === null) {
            redirect('/documents/templates/create');
        }
        $data['company_id'] = $companyId;
        // المدير يمكنه إنشاء قالب عام للشركة، وغيره ينشئ قوالبه الشخصية
        $data['created_by'] = ($this->isManager() && Request::input('company_wide')) ? null : Auth::id();

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
        $template = $this->findInCompany((int) $params['id'], $companyId);
        $this->guardEdit($template);

        View::render('documents::templates.form', [
            'pageTitle' => 'تعديل قالب',
            'template' => $template,
            'positions' => self::POSITIONS,
            'isManager' => $this->isManager(),
            'stamps' => \App\Core\CompanyStamp::usableBy(Auth::id(), $companyId, $this->isManager()),
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $template = $this->findInCompany((int) $params['id'], $companyId);
        $this->guardEdit($template);
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
        $template = $this->findInCompany((int) $params['id'], $companyId);
        $this->guardEdit($template);
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

    private function isManager(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || \App\Core\Permission::check('documents.manage');
    }

    /** القوالب جزء من مساحة الكتابة: متاحة لكل من يرى المستندات. */
    private function guardView(): void
    {
        if (!\App\Core\Permission::check('documents.view')) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
    }

    /** تعديل/حذف/مشاركة القالب: صاحبه أو المدير (قوالب الشركة العامة للمدير). */
    private function canEditTemplate(array $template): bool
    {
        if ($this->isManager()) {
            return true;
        }
        return !empty($template['created_by']) && (int) $template['created_by'] === Auth::id();
    }

    private function guardEdit(array $template): void
    {
        if (!$this->canEditTemplate($template)) {
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

        // ختم القالب: يجب أن يحق للمستخدم استخدامه (ملكه/مشارَك معه/مكتبة الشركة للمدير)
        $stampId = (int) Request::input('stamp_id', 0) ?: null;
        if ($stampId && !\App\Core\CompanyStamp::findUsableBy($stampId, Auth::id(), (int) Auth::companyId(), $this->isManager())) {
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

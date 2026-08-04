<?php

namespace Modules\Documents\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HtmlSanitizer;
use App\Core\Notification;
use App\Core\Request;
use App\Core\View;
use Modules\Documents\Models\Document;
use Modules\Documents\Models\DocumentLog;
use Modules\Documents\Models\DocumentSetting;
use Modules\Documents\Models\DocumentVersion;
use Modules\Documents\Models\DocumentTemplate;

class DocumentController
{
    private const TYPES = ['general', 'letter', 'decision', 'certificate', 'authorization'];
    private const STATUSES = ['draft', 'pending_approval', 'approved', 'signed', 'archived'];
    private const CONFIDENTIALITIES = ['normal', 'internal', 'confidential', 'secret'];

    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('documents.view')) {
            $this->forbidden();
            return;
        }

        $filters = $this->currentFilters();
        $page = max(1, (int) Request::query('page', 1));
        $result = Document::paginate($companyId, $this->canManage(), Auth::id(), $page, 15, $filters);

        View::render('documents::index', [
            'pageTitle' => 'المستندات',
            'documents' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 15,
            'filters' => $filters,
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'canManage' => $this->canManage(),
            'canCreate' => $this->can('documents.create'),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('documents.create')) {
            $this->forbidden();
            return;
        }

        View::render('documents::form', [
            'pageTitle' => 'مستند جديد',
            'document' => null,
            'templates' => DocumentTemplate::forCompany($companyId),
            'types' => self::TYPES,
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('documents.create')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/documents/create');

        $data = $this->validated($companyId);
        if ($data === null) {
            redirect('/documents/create');
        }

        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();
        $data['verify_token'] = bin2hex(random_bytes(16));

        if ($data['visibility'] === 'private') {
            // خاص: يُعتمد تلقائياً فوراً بلا مسار موافقة، ويُمنح رقماً مباشرة
            $data['status'] = 'approved';
            $data['approved_by'] = Auth::id();
            $data['approved_at'] = date('Y-m-d H:i:s');
            $data['number'] = DocumentSetting::generateNumber($companyId);
        } else {
            $data['status'] = 'draft';
        }

        $documentId = Document::create($data);
        DocumentLog::add($documentId, Auth::id(), 'created', 'تم إنشاء المستند');

        ActivityLog::log('documents.create', 'document', $documentId, "إنشاء مستند: {$data['title']}");
        flash_set('success', 'تم إنشاء المستند.');
        redirect('/documents/' . $documentId);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);

        View::render('documents::show', [
            'pageTitle' => $document['title'],
            'document' => $document,
            'logs' => DocumentLog::forDocument($document['id']),
            'versions' => DocumentVersion::forDocument($document['id']),
            'canEdit' => $this->canEditDocument($document),
            'canDelete' => $this->canDeleteDocument($document),
            'canManage' => $this->canManage(),
            'canApprove' => $this->can('documents.approve'),
            'canSign' => $this->can('documents.sign'),
            'mySignatures' => \App\Core\UserSignature::forUser(Auth::id()),
        ]);
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditDocument($document)) {
            $this->forbidden();
            return;
        }

        View::render('documents::form', [
            'pageTitle' => 'تعديل مستند',
            'document' => $document,
            'templates' => DocumentTemplate::forCompany($companyId),
            'types' => self::TYPES,
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditDocument($document)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/documents/' . $document['id'] . '/edit');

        $data = $this->validated($companyId);
        if ($data === null) {
            redirect('/documents/' . $document['id'] . '/edit');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        // تحويل مستند عام لخاص أثناء التعديل يُعتمد فوراً بنفس منطق الإنشاء المباشر
        if ($data['visibility'] === 'private' && empty($document['number'])) {
            $data['status'] = 'approved';
            $data['approved_by'] = Auth::id();
            $data['approved_at'] = date('Y-m-d H:i:s');
            $data['number'] = DocumentSetting::generateNumber($companyId);
        }

        // لقطة إصدار قبل التعديل إن تغيّر المحتوى أو العنوان (سجل الإصدارات)
        $contentChanged = array_key_exists('content', $data) && (string) $data['content'] !== (string) ($document['content'] ?? '');
        $titleChanged = array_key_exists('title', $data) && (string) $data['title'] !== (string) ($document['title'] ?? '');
        if ($contentChanged || $titleChanged) {
            DocumentVersion::snapshot($document, Auth::id());
        }

        Document::update($document['id'], $data);
        DocumentLog::add($document['id'], Auth::id(), 'updated', 'تم تعديل بيانات المستند');

        ActivityLog::log('documents.update', 'document', $document['id'], "تعديل مستند: {$data['title']}");
        flash_set('success', 'تم حفظ التعديلات.');
        redirect('/documents/' . $document['id']);
    }

    /** عرض محتوى إصدار سابق (للقراءة) مع إتاحة استعادته. */
    public function viewVersion(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        $version = DocumentVersion::find((int) $params['versionId']);
        if (!$version || (int) $version['document_id'] !== (int) $document['id']) {
            flash_set('error', 'الإصدار غير موجود.');
            redirect('/documents/' . $document['id']);
        }

        View::render('documents::version_view', [
            'pageTitle' => 'إصدار سابق #' . $version['version_no'],
            'document' => $document,
            'version' => $version,
            'canEdit' => $this->canEditDocument($document),
        ]);
    }

    /** استعادة محتوى المستند إلى إصدار سابق (مع حفظ لقطة للحالة الحالية أولاً). */
    public function restoreVersion(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditDocument($document)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/documents/' . $document['id']);

        $version = DocumentVersion::find((int) $params['versionId']);
        if (!$version || (int) $version['document_id'] !== (int) $document['id']) {
            flash_set('error', 'الإصدار غير موجود.');
            redirect('/documents/' . $document['id']);
        }

        // نحفظ لقطة للحالة الحالية حتى تكون الاستعادة نفسها قابلة للتراجع
        DocumentVersion::snapshot($document, Auth::id());
        Document::update($document['id'], [
            'title' => $version['title'],
            'content' => $version['content'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        DocumentLog::add($document['id'], Auth::id(), 'updated', 'استعادة إصدار سابق #' . $version['version_no']);
        ActivityLog::log('documents.restore_version', 'document', $document['id'], "استعادة إصدار #{$version['version_no']} للمستند: {$document['title']}");

        flash_set('success', 'تمت استعادة الإصدار #' . $version['version_no'] . '.');
        redirect('/documents/' . $document['id']);
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canDeleteDocument($document)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/documents/' . $document['id']);

        Document::delete($document['id']);
        ActivityLog::log('documents.delete', 'document', $document['id'], "حذف مستند: {$document['title']}");
        flash_set('success', 'تم حذف المستند.');
        redirect('/documents');
    }

    public function updateStatus(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/documents/' . $document['id']);

        $action = Request::input('action');
        $isCreator = (int) $document['created_by'] === Auth::id();

        switch ($action) {
            case 'submit':
                if ($document['status'] !== 'draft' || $document['visibility'] !== 'public') {
                    flash_set('error', 'لا يمكن إرسال هذا المستند للاعتماد في حالته الحالية.');
                    redirect('/documents/' . $document['id']);
                }
                if (!$this->canManage() && !$isCreator) {
                    $this->forbidden();
                    return;
                }
                Document::update($document['id'], ['status' => 'pending_approval', 'updated_at' => date('Y-m-d H:i:s')]);
                DocumentLog::add($document['id'], Auth::id(), 'submitted', 'تم إرسال المستند لطلب الاعتماد');
                flash_set('success', 'تم إرسال المستند لطلب الاعتماد.');
                break;

            case 'approve':
                if ($document['status'] !== 'pending_approval') {
                    flash_set('error', 'هذا المستند ليس بانتظار الاعتماد.');
                    redirect('/documents/' . $document['id']);
                }
                if (!$this->canManage() && !$this->can('documents.approve')) {
                    $this->forbidden();
                    return;
                }
                $update = [
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                if (empty($document['number'])) {
                    $update['number'] = DocumentSetting::generateNumber($companyId);
                }
                Document::update($document['id'], $update);
                DocumentLog::add($document['id'], Auth::id(), 'approved', 'تم اعتماد المستند');
                Notification::send((int) $document['created_by'], 'تم اعتماد مستندك', $document['title'], route('/documents/' . $document['id']));
                flash_set('success', 'تم اعتماد المستند.');
                break;

            case 'sign':
                if ($document['status'] !== 'approved') {
                    flash_set('error', 'هذا المستند ليس جاهزاً للتوقيع.');
                    redirect('/documents/' . $document['id']);
                }
                if (!$this->canManage() && !$this->can('documents.sign')) {
                    $this->forbidden();
                    return;
                }
                // التوقيع بتوقيع الموقّع نفسه فقط (المختار من مكتبة تواقيعه) - لقطة تُخزَّن
                // على المستند فلا يتأثر لو حذف الموقّع توقيعه لاحقاً.
                $signUpdate = [
                    'status' => 'signed',
                    'signed_by' => Auth::id(),
                    'signed_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $sigId = (int) Request::input('signature_id', 0);
                if ($sigId) {
                    $sig = \App\Core\UserSignature::findForUser($sigId, Auth::id());
                    if ($sig) {
                        $signUpdate['signature_file'] = $sig['image'];
                    }
                }
                Document::update($document['id'], $signUpdate);
                DocumentLog::add($document['id'], Auth::id(), 'signed', 'تم توقيع المستند');
                Notification::send((int) $document['created_by'], 'تم توقيع مستندك', $document['title'], route('/documents/' . $document['id']));
                flash_set('success', 'تم توقيع المستند.');
                break;

            case 'archive':
                if ($document['status'] === 'archived') {
                    flash_set('error', 'المستند مؤرشف بالفعل.');
                    redirect('/documents/' . $document['id']);
                }
                if (!$this->canManage() && !$isCreator) {
                    $this->forbidden();
                    return;
                }
                Document::update($document['id'], [
                    'status' => 'archived',
                    'previous_status' => $document['status'],
                    'archived_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                DocumentLog::add($document['id'], Auth::id(), 'archived', 'تمت أرشفة المستند');
                flash_set('success', 'تمت أرشفة المستند.');
                break;

            case 'restore':
                if ($document['status'] !== 'archived') {
                    flash_set('error', 'المستند ليس مؤرشفاً.');
                    redirect('/documents/' . $document['id']);
                }
                if (!$this->canManage()) {
                    $this->forbidden();
                    return;
                }
                $restoredStatus = $document['previous_status'] ?: 'draft';
                Document::update($document['id'], [
                    'status' => $restoredStatus,
                    'previous_status' => null,
                    'archived_at' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                DocumentLog::add($document['id'], Auth::id(), 'restored', 'تمت استعادة المستند من الأرشيف');
                flash_set('success', 'تمت استعادة المستند من الأرشيف.');
                break;

            default:
                flash_set('error', 'إجراء غير معروف.');
        }

        redirect('/documents/' . $document['id']);
    }

    public function print(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);

        $template = $document['template_id'] ? DocumentTemplate::find((int) $document['template_id']) : null;
        if ($template && (int) $template['company_id'] !== $companyId) {
            $template = null;
        }

        $settings = DocumentSetting::getOrCreate($companyId);
        $company = Database::first('SELECT * FROM companies WHERE id = :id', ['id' => $companyId]);

        // التوقيع: توقيع الموقّع الشخصي المختار (لقطة على المستند)، وإلا توقيع الشركة القديم.
        $signatureUrl = null;
        if (!empty($document['signature_file'])) {
            $signatureUrl = route('/media/signatures/' . $companyId . '/' . $document['signature_file']);
        } elseif (!empty($settings['signature_image'])) {
            $signatureUrl = route('/media/documents/' . $companyId . '/' . $settings['signature_image']);
        }

        // الختم: ختم القالب من مكتبة الأختام، وإلا ختم الشركة القديم.
        $stampUrl = null;
        if ($template && !empty($template['stamp_id'])) {
            $stamp = \App\Core\CompanyStamp::findForCompany((int) $template['stamp_id'], $companyId);
            if ($stamp) {
                $stampUrl = \App\Core\CompanyStamp::imageUrl($stamp);
            }
        }
        if (!$stampUrl && !empty($settings['stamp_image'])) {
            $stampUrl = route('/media/documents/' . $companyId . '/' . $settings['stamp_image']);
        }

        // اسم الموقّع: المستخدم الذي وقّع فعلاً، وإلا الاسم المعرّف بالإعدادات.
        $signerName = $settings['signer_name'] ?? null;
        if (!empty($document['signed_by'])) {
            $signer = Database::first('SELECT name FROM users WHERE id = :id', ['id' => $document['signed_by']]);
            if ($signer) {
                $signerName = $signer['name'];
            }
        }

        View::render('documents::print', [
            'document' => $document,
            'template' => $template,
            'settings' => $settings,
            'company' => $company,
            'signatureUrl' => $signatureUrl,
            'stampUrl' => $stampUrl,
            'signerName' => $signerName,
            'verifyUrl' => !empty($document['verify_token']) ? base_url('documents/verify/' . $document['verify_token']) : null,
        ], '');
    }

    /**
     * صفحة تحقّق عامة (بلا تسجيل دخول) للتأكد من صحة مستند عبر رمزه.
     * تعرض بيانات موجزة تُثبت الأصالة (الرقم/العنوان/الحالة/الجهة/التواريخ) دون
     * محتوى المستند نفسه حفاظاً على الخصوصية.
     */
    public function verify(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $document = ctype_xdigit($token) ? Document::findByToken($token) : null;

        View::render('documents::verify', [
            'pageTitle' => 'التحقق من مستند',
            'document' => $document,
            'typeLabels' => Document::typeLabels(),
            'statusLabels' => Document::statusLabels(),
        ], '');
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('documents::no-company', ['pageTitle' => 'المستندات']);
            exit;
        }
        return $companyId;
    }

    private function can(string $key): bool
    {
        return \App\Core\Permission::check($key);
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || $this->can('documents.manage');
    }

    /** لا تعديل بعد الاعتماد حتى تبقى المستندات المعتمدة/الموقّعة سجلاً رسمياً موثوقاً. */
    private function canEditDocument(array $document): bool
    {
        if (!in_array($document['status'], ['draft', 'pending_approval'], true)) {
            return false;
        }
        if ($this->canManage()) {
            return true;
        }
        return $this->can('documents.edit') && (int) $document['created_by'] === Auth::id();
    }

    private function canDeleteDocument(array $document): bool
    {
        if ($this->canManage()) {
            return true;
        }
        if (!in_array($document['status'], ['draft', 'pending_approval'], true)) {
            return false;
        }
        return $this->can('documents.delete') && (int) $document['created_by'] === Auth::id();
    }

    private function findVisible(int $id, int $companyId): array
    {
        $document = Document::find($id);
        if (!$document || (int) $document['company_id'] !== $companyId) {
            flash_set('error', 'المستند غير موجود.');
            redirect('/documents');
        }

        if (!$this->can('documents.view')) {
            $this->forbidden();
            exit;
        }

        $visible = $this->canManage()
            || (int) $document['created_by'] === Auth::id()
            || ($document['visibility'] === 'public' && $document['status'] !== 'draft');
        if (!$visible) {
            $this->forbidden();
            exit;
        }

        return $document;
    }

    private function currentFilters(): array
    {
        $filters = [];
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        $type = Request::query('type', '');
        if (in_array($type, self::TYPES, true)) {
            $filters['type'] = $type;
        }
        $status = Request::query('status', '');
        if (in_array($status, self::STATUSES, true)) {
            $filters['status'] = $status;
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

    private function validated(int $companyId): ?array
    {
        $title = trim((string) Request::input('title', ''));
        $type = Request::input('type', 'general');
        $visibility = Request::input('visibility', 'public');
        $confidentiality = Request::input('confidentiality', 'normal');
        $templateId = (int) Request::input('template_id', 0) ?: null;
        $followUpDate = Request::input('follow_up_date') ?: null;
        $expiryDate = Request::input('expiry_date') ?: null;
        $content = HtmlSanitizer::sanitize(Request::input('content', ''));

        if ($title === '') {
            flash_set('error', 'عنوان المستند مطلوب.');
            return null;
        }
        if (!in_array($type, self::TYPES, true)) {
            $type = 'general';
        }
        if (!in_array($visibility, ['public', 'private'], true)) {
            $visibility = 'public';
        }
        if (!in_array($confidentiality, self::CONFIDENTIALITIES, true)) {
            $confidentiality = 'normal';
        }

        if ($templateId) {
            $template = DocumentTemplate::find($templateId);
            if (!$template || (int) $template['company_id'] !== $companyId) {
                flash_set('error', 'القالب المختار غير صالح.');
                return null;
            }
        }

        return [
            'title' => $title,
            'type' => $type,
            'visibility' => $visibility,
            'confidentiality' => $confidentiality,
            'template_id' => $templateId,
            'follow_up_date' => $followUpDate,
            'expiry_date' => $expiryDate,
            'content' => $content,
        ];
    }
}

<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\CompanyStamp;
use App\Core\Database;
use App\Core\HtmlSanitizer;
use App\Core\Notification;
use App\Core\Permission;
use App\Core\Uploads;
use App\Core\UserSignature;
use App\Core\View;
use Modules\Documents\Models\Document;
use Modules\Documents\Models\DocumentLog;
use Modules\Documents\Models\DocumentSetting;
use Modules\Documents\Models\DocumentTemplate;
use Modules\Documents\Models\DocumentVersion;
use Modules\Mobileapi\Support\Api;

/**
 * نقاط JSON للمستندات 2.0 - نفس فلسفة DocumentController الويب:
 * مساحات (ملفي/مشاركة معي/الشركة)، مشاركة بأدوار مشاهد/محرر،
 * إصدار رسمي بالتوقيع (الرقم يُمنح عند الإصدار ثم يُقفل المستند)،
 * وسجل نسخ مع فروقات كلمة-بكلمة.
 */
class DocumentsApiController
{
    private const TYPES = ['general', 'letter', 'decision', 'certificate', 'authorization'];
    private const STATUSES = ['draft', 'pending_approval', 'approved', 'signed', 'archived'];
    private const CONFIDENTIALITIES = ['normal', 'internal', 'confidential', 'secret'];
    private const SCOPES = ['mine', 'shared', 'company'];

    // ---------- القوائم ----------

    /** GET /api/v1/documents?scope=&q=&type=&status=&page= */
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('documents.view');

        $scope = (string) Api::input('scope', 'mine');
        if (!in_array($scope, self::SCOPES, true)) {
            $scope = 'mine';
        }

        $filters = [];
        $q = trim((string) Api::input('q', ''));
        if ($q !== '') {
            $filters['q'] = $q;
        }
        $type = (string) Api::input('type', '');
        if (in_array($type, self::TYPES, true)) {
            $filters['type'] = $type;
        }
        $status = (string) Api::input('status', '');
        if (in_array($status, self::STATUSES, true)) {
            $filters['status'] = $status;
        }

        $page = max(1, (int) Api::input('page', 1));
        $result = Document::paginate($companyId, $scope, $this->canManage(), Auth::id(), $page, 15, $filters);

        Api::ok([
            'documents' => array_map([$this, 'summaryPayload'], $result['rows']),
            'total' => (int) $result['total'],
            'page' => $page,
            'per_page' => 15,
            'shared_count' => Document::countSharedWith($companyId, Auth::id()),
            'can_create' => Permission::check('documents.create'),
            'can_manage' => $this->canManage(),
        ]);
    }

    /** GET /api/v1/documents/users - مستخدمو الشركة (لاختيار المشاركة). */
    public function companyUsers(): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('documents.view');

        $rows = Database::select(
            'SELECT id, name FROM users WHERE company_id = :c AND status = "active" ORDER BY name',
            ['c' => $companyId]
        );
        Api::ok(['users' => array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name']], $rows)]);
    }

    /** GET /api/v1/documents/templates - القوالب المتاحة للكاتب: قوالب الشركة العامة + ملكه + المشارَكة معه. */
    public function templates(): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('documents.view');

        Api::ok(['templates' => array_map(
            [$this, 'pickerPayload'],
            DocumentTemplate::usableBy(Auth::id(), $companyId)
        )]);
    }

    /** GET /api/v1/documents/signatures - التواقيع المتاحة للكاتب (ملكه + المشارَكة معه). */
    public function signatures(): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('documents.view');

        Api::ok(['signatures' => array_map(
            [$this, 'pickerPayload'],
            UserSignature::usableBy(Auth::id(), $companyId)
        )]);
    }

    /** GET /api/v1/documents/stamps - الأختام المتاحة للكاتب (مكتبة الشركة + ملكه + المشارَكة معه). */
    public function stamps(): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('documents.view');

        Api::ok(['stamps' => array_map(
            [$this, 'pickerPayload'],
            CompanyStamp::usableBy(Auth::id(), $companyId, $this->canManage())
        )]);
    }

    /** GET /api/v1/documents/{id} - التفاصيل الكاملة. */
    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);

        $isOwner = (int) $document['created_by'] === Auth::id();
        $myShareRole = Document::shareRole((int) $document['id'], Auth::id());
        $canSignNow = in_array($document['status'], ['draft', 'pending_approval', 'approved'], true)
            && ($isOwner || $this->canManage() || Permission::check('documents.sign'));

        Api::ok([
            'document' => $this->detailPayload($document),
            'logs' => array_map(fn ($l) => [
                'id' => (int) $l['id'],
                'action' => $l['action'],
                'description' => $l['description'],
                'user_name' => $l['user_name'] ?? '',
                'created_at' => $l['created_at'],
            ], DocumentLog::forDocument((int) $document['id'])),
            'versions' => array_map(fn ($v) => [
                'id' => (int) $v['id'],
                'version_no' => (int) $v['version_no'],
                'title' => $v['title'],
                'saved_by_name' => $v['saved_by_name'] ?? null,
                'created_at' => $v['created_at'],
            ], DocumentVersion::forDocument((int) $document['id'])),
            'shares' => array_map(fn ($s) => [
                'user_id' => (int) $s['user_id'],
                'user_name' => $s['user_name'] ?? '',
                'role' => $s['role'],
            ], Document::shares((int) $document['id'])),
            'comments' => $this->comments((int) $document['id']),
            'my_signatures' => array_map([$this, 'pickerPayload'], UserSignature::usableBy(Auth::id(), $companyId)),
            'my_stamps' => array_map([$this, 'pickerPayload'], CompanyStamp::usableBy(Auth::id(), $companyId, $this->canManage())),
            'is_owner' => $isOwner,
            'my_share_role' => $myShareRole,
            'can_edit' => $this->canEditDocument($document),
            'can_delete' => $this->canDeleteDocument($document),
            'can_sign' => $canSignNow,
            'can_share' => $isOwner || $this->canManage(),
            'can_archive' => $document['status'] !== 'archived' && ($this->canManage() || $isOwner),
            'can_restore' => $document['status'] === 'archived' && $this->canManage(),
            'can_duplicate' => Permission::check('documents.create') || $this->canManage(),
        ]);
    }

    /** GET /api/v1/documents/{id}/paper - ورقة المستند الرسمية HTML ذاتية الاكتفاء (الصور data URIs). */
    public function paper(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);

        $template = null;
        if (!empty($document['template_id'])) {
            $template = DocumentTemplate::find((int) $document['template_id']);
            if ($template && (int) $template['company_id'] !== $companyId) {
                $template = null;
            }
        }
        $settings = DocumentSetting::getOrCreate($companyId);
        $company = Database::first('SELECT * FROM companies WHERE id = :id', ['id' => $companyId]);

        // كل الصور تُضمَّن data URIs لأن /media يتطلب جلسة متصفح.
        // التوقيع: لقطة الإصدار الرسمي، ثم التوقيع المختار أثناء الكتابة، وإلا
        // توقيع الشركة القديم من الإعدادات (وهذا الأخير بعد الإصدار فقط).
        $signatureUrl = null;
        if (!empty($document['signature_file'])) {
            $signatureUrl = Uploads::dataUri(BASE_PATH . '/storage/uploads/signatures/' . $companyId . '/' . $document['signature_file']);
        } elseif (!empty($document['signature_id'])) {
            $chosenSig = UserSignature::find((int) $document['signature_id']);
            if ($chosenSig && (int) $chosenSig['company_id'] === $companyId) {
                $signatureUrl = Uploads::dataUri(BASE_PATH . '/storage/uploads/signatures/' . $companyId . '/' . $chosenSig['image']);
            }
        } elseif ($document['status'] === 'signed' && !empty($settings['signature_image'])) {
            $signatureUrl = Uploads::dataUri(BASE_PATH . '/storage/uploads/documents/' . $companyId . '/' . $settings['signature_image']);
        }

        // الختم: ختم المستند المختار أثناء الكتابة، ثم ختم القالب، وإلا ختم الشركة القديم.
        $stampUrl = null;
        if (!empty($document['stamp_id'])) {
            $stamp = CompanyStamp::findForCompany((int) $document['stamp_id'], $companyId);
            if ($stamp) {
                $stampUrl = Uploads::dataUri(BASE_PATH . '/storage/uploads/stamps/' . $companyId . '/' . $stamp['image']);
            }
        }
        if (!$stampUrl && $template && !empty($template['stamp_id'])) {
            $stamp = CompanyStamp::findForCompany((int) $template['stamp_id'], $companyId);
            if ($stamp) {
                $stampUrl = Uploads::dataUri(BASE_PATH . '/storage/uploads/stamps/' . $companyId . '/' . $stamp['image']);
            }
        }
        if (!$stampUrl && $document['status'] === 'signed' && !empty($settings['stamp_image'])) {
            $stampUrl = Uploads::dataUri(BASE_PATH . '/storage/uploads/documents/' . $companyId . '/' . $settings['stamp_image']);
        }

        $bgUrl = null;
        if ($template && !empty($template['background_image'])) {
            $bgUrl = Uploads::dataUri(BASE_PATH . '/storage/uploads/documents/' . $companyId . '/' . $template['background_image']);
        }

        // سطرا الموقّع: ما كتبه الكاتب أولاً (والاسم اختياري عمداً)، وإلا السلوك
        // القديم: اسم من وقّع فعلاً أو ما هو معرَّف في إعدادات الشركة.
        $signerTitle = $document['signer_title'] ?? null;
        $signerName = $document['signer_name'] ?? null;
        if ($signerTitle === null && $signerName === null) {
            $signerName = $settings['signer_name'] ?? null;
            $signerTitle = $settings['signer_title'] ?? null;
            if (!empty($document['signed_by'])) {
                $signer = Database::first('SELECT name FROM users WHERE id = :id', ['id' => $document['signed_by']]);
                if ($signer) {
                    $signerName = $signer['name'];
                }
            }
        }

        $_GET['embed'] = '1';
        $html = View::renderPartial('documents::print', [
            'document' => $document,
            'template' => $template,
            'settings' => $settings,
            'company' => $company,
            'signatureUrl' => $signatureUrl,
            'stampUrl' => $stampUrl,
            'bgUrl' => $bgUrl,
            'signerName' => $signerName,
            'signerTitle' => $signerTitle,
            'verifyUrl' => !empty($document['verify_token']) ? base_url('documents/verify/' . $document['verify_token']) : null,
            'typeLabels' => Document::typeLabels(),
        ]);

        Api::ok(['html' => $html]);
    }

    // ---------- الإنشاء والتحرير ----------

    /** POST /api/v1/documents */
    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('documents.create');

        $data = $this->validated($companyId);
        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();
        $data['status'] = 'draft';
        $data['verify_token'] = bin2hex(random_bytes(16));

        $documentId = Document::create($data);
        DocumentLog::add($documentId, Auth::id(), 'created', 'تم إنشاء المستند من تطبيق الجوال');
        ActivityLog::log('documents.create', 'document', (string) $documentId, 'إنشاء مستند: ' . $data['title']);

        Api::ok(['id' => $documentId], 201);
    }

    /** POST /api/v1/documents/{id} - تحرير (يأخذ نسخة قبل الكتابة). */
    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditDocument($document)) {
            Api::error('لا يمكنك تعديل هذا المستند (قد يكون صادراً رسمياً أو ليس لديك دور محرر).', 403, 'forbidden');
        }

        $data = $this->validated($companyId);
        $data['updated_at'] = date('Y-m-d H:i:s');

        $contentChanged = ($data['content'] ?? '') !== (string) ($document['content'] ?? '');
        $titleChanged = $data['title'] !== $document['title'];
        if ($contentChanged || $titleChanged) {
            DocumentVersion::snapshot($document, Auth::id());
        }

        Document::update((int) $document['id'], $data);
        DocumentLog::add((int) $document['id'], Auth::id(), 'updated', 'تم تعديل المستند من تطبيق الجوال');

        if (($contentChanged || $titleChanged) && (int) $document['created_by'] !== Auth::id()) {
            Notification::send(
                (int) $document['created_by'],
                '✏️ عُدّل مستندك',
                (Auth::user()['name'] ?? '') . ' عدّل «' . $data['title'] . '» — افتح سجل الإصدارات لرؤية التغييرات.',
                route('/documents/' . $document['id'])
            );
        }
        ActivityLog::log('documents.update', 'document', (string) $document['id'], 'تعديل مستند: ' . $data['title']);

        Api::ok(['id' => (int) $document['id']]);
    }

    /** POST /api/v1/documents/{id}/delete */
    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canDeleteDocument($document)) {
            Api::error('لا تملك صلاحية حذف هذا المستند.', 403, 'forbidden');
        }

        Document::delete((int) $document['id']);
        ActivityLog::log('documents.delete', 'document', (string) $document['id'], 'حذف مستند: ' . $document['title']);
        Api::ok(['message' => 'تم حذف المستند.']);
    }

    /** POST /api/v1/documents/{id}/duplicate - نسخة مسودة جديدة. */
    public function duplicate(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!Permission::check('documents.create') && !$this->canManage()) {
            Api::error('لا تملك صلاحية إنشاء مستند.', 403, 'forbidden');
        }
        $document = $this->findVisible((int) $params['id'], $companyId);

        $newId = Document::create([
            'company_id' => $companyId,
            'type' => $document['type'],
            'visibility' => $document['visibility'],
            'confidentiality' => $document['confidentiality'],
            'status' => 'draft',
            'title' => mb_substr($document['title'], 0, 240) . ' (نسخة)',
            'content' => $document['content'],
            'template_id' => $document['template_id'],
            // تُنقل إعدادات الورقة ليعدّل الناسخ المحتوى فقط؛ الرقم والتوقيع
            // الرسمي يبقيان حكراً على إصدار النسخة الجديدة نفسها.
            'signature_id' => $document['signature_id'] ?? null,
            'stamp_id' => $document['stamp_id'] ?? null,
            'signer_title' => $document['signer_title'] ?? null,
            'signer_name' => $document['signer_name'] ?? null,
            'verify_token' => bin2hex(random_bytes(16)),
            'created_by' => Auth::id(),
        ]);

        DocumentLog::add($newId, Auth::id(), 'created', 'أُنشئ كنسخة من مستند آخر');
        DocumentLog::add((int) $document['id'], Auth::id(), 'duplicated', 'أُخذت منه نسخة مسودة');
        ActivityLog::log('documents.duplicate', 'document', (string) $newId, 'نسخ مستند من الجوال');

        Api::ok(['id' => $newId], 201);
    }

    // ---------- الإصدار الرسمي والأرشفة ----------

    /** POST /api/v1/documents/{id}/status  {action: sign|archive|restore, signature_id?} */
    public function updateStatus(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        $isOwner = (int) $document['created_by'] === Auth::id();

        $action = (string) Api::input('action', '');
        switch ($action) {
            case 'sign':
                if (!in_array($document['status'], ['draft', 'pending_approval', 'approved'], true)) {
                    Api::error('هذا المستند ليس جاهزاً للإصدار الرسمي.', 422, 'invalid_transition');
                }
                if (!$isOwner && !$this->canManage() && !Permission::check('documents.sign')) {
                    Api::error('لا تملك صلاحية الإصدار الرسمي.', 403, 'forbidden');
                }

                $update = [
                    'status' => 'signed',
                    'signed_by' => Auth::id(),
                    'signed_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $number = $document['number'];
                if (empty($number)) {
                    $number = DocumentSetting::generateNumber($companyId);
                    $update['number'] = $number;
                }

                // توقيع الإصدار: اختيار لحظة الإصدار، وإلا التوقيع المختار أثناء الكتابة.
                $signatureId = (int) Api::input('signature_id', 0) ?: (int) ($document['signature_id'] ?? 0);
                if ($signatureId > 0) {
                    $signature = UserSignature::findUsableBy($signatureId, Auth::id());
                    if ($signature) {
                        $update['signature_file'] = $signature['image'];
                    }
                }

                Document::update((int) $document['id'], $update);
                DocumentLog::add((int) $document['id'], Auth::id(), 'signed', 'إصدار رسمي وتوقيع من الجوال — رقم ' . $number);
                if (!$isOwner) {
                    Notification::send((int) $document['created_by'], '🔏 صدر مستندك رسمياً', $document['title'] . ' — رقم ' . $number, route('/documents/' . $document['id']));
                }
                ActivityLog::log('documents.sign', 'document', (string) $document['id'], 'إصدار رسمي: ' . $document['title']);

                Api::ok(['status' => 'signed', 'number' => $number]);
                return;

            case 'archive':
                if ($document['status'] === 'archived') {
                    Api::error('المستند مؤرشف مسبقاً.', 422, 'invalid_transition');
                }
                if (!$this->canManage() && !$isOwner) {
                    Api::error('لا تملك صلاحية أرشفة هذا المستند.', 403, 'forbidden');
                }
                Document::update((int) $document['id'], [
                    'status' => 'archived',
                    'previous_status' => $document['status'],
                    'archived_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                DocumentLog::add((int) $document['id'], Auth::id(), 'archived', 'تمت أرشفة المستند');
                Api::ok(['status' => 'archived']);
                return;

            case 'restore':
                if ($document['status'] !== 'archived' || !$this->canManage()) {
                    Api::error('لا يمكن استعادة هذا المستند.', 403, 'forbidden');
                }
                Document::update((int) $document['id'], [
                    'status' => $document['previous_status'] ?: 'draft',
                    'previous_status' => null,
                    'archived_at' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                DocumentLog::add((int) $document['id'], Auth::id(), 'restored', 'تمت استعادة المستند من الأرشيف');
                Api::ok(['status' => $document['previous_status'] ?: 'draft']);
                return;

            default:
                Api::error('إجراء غير معروف.', 422, 'validation');
        }
    }

    // ---------- المشاركة ----------

    /** POST /api/v1/documents/{id}/share  {user_id, role: viewer|editor} */
    public function share(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        if ((int) $document['created_by'] !== Auth::id() && !$this->canManage()) {
            Api::error('المشاركة متاحة لمالك المستند أو المدير فقط.', 403, 'forbidden');
        }

        $userId = (int) Api::input('user_id', 0);
        $target = Database::first(
            'SELECT id, name FROM users WHERE id = :id AND company_id = :c AND status = "active" LIMIT 1',
            ['id' => $userId, 'c' => $companyId]
        );
        if (!$target || $userId === (int) $document['created_by']) {
            Api::error('اختر موظفاً صحيحاً (غير مالك المستند).', 422, 'validation');
        }

        $role = (string) Api::input('role', 'viewer') === 'editor' ? 'editor' : 'viewer';
        Document::setShare((int) $document['id'], $userId, $role, Auth::id());
        DocumentLog::add((int) $document['id'], Auth::id(), 'shared', 'مشاركة مع ' . $target['name'] . ' (' . ($role === 'editor' ? 'تعديل' : 'مشاهدة') . ')');
        Notification::send(
            $userId,
            '📄 شُورك معك مستند',
            $document['title'] . ($role === 'editor' ? ' — يمكنك التعديل' : ' — للمشاهدة'),
            route('/documents/' . $document['id'])
        );

        Api::ok(['message' => 'شُورك المستند مع ' . $target['name'] . '.'], 201);
    }

    /** POST /api/v1/documents/{id}/share/{userId}/unshare */
    public function unshare(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        if ((int) $document['created_by'] !== Auth::id() && !$this->canManage()) {
            Api::error('المشاركة متاحة لمالك المستند أو المدير فقط.', 403, 'forbidden');
        }

        Document::removeShare((int) $document['id'], (int) $params['userId']);
        DocumentLog::add((int) $document['id'], Auth::id(), 'unshared', 'إلغاء مشاركة مستخدم');
        Api::ok(['message' => 'أُلغيت المشاركة.']);
    }

    // ---------- التعليقات ----------

    /** POST /api/v1/documents/{id}/comments  {body} */
    public function comment(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);

        $body = trim((string) Api::input('body', ''));
        if ($body === '') {
            Api::error('اكتب نص التعليق.', 422, 'validation');
        }

        Database::insert('documents_comments', [
            'document_id' => (int) $document['id'],
            'user_id' => Auth::id(),
            'body' => mb_substr($body, 0, 2000),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        DocumentLog::add((int) $document['id'], Auth::id(), 'commented', 'إضافة تعليق');

        // مالك المستند + كل معلّق سابق (عدا الكاتب الحالي).
        $targets = [(int) $document['created_by']];
        foreach (Database::select('SELECT DISTINCT user_id FROM documents_comments WHERE document_id = :d', ['d' => $document['id']]) as $row) {
            $targets[] = (int) $row['user_id'];
        }
        foreach (array_unique($targets) as $target) {
            if ($target && $target !== Auth::id()) {
                Notification::send($target, '💬 تعليق جديد على مستند', $document['title'], route('/documents/' . $document['id']));
            }
        }

        Api::ok(['message' => 'أُضيف تعليقك.'], 201);
    }

    // ---------- النسخ والفروقات ----------

    /** GET /api/v1/documents/{id}/versions/{versionId} */
    public function viewVersion(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);

        $version = DocumentVersion::find((int) $params['versionId']);
        if (!$version || (int) $version['document_id'] !== (int) $document['id']) {
            Api::error('الإصدار غير موجود.', 404, 'not_found');
        }

        Api::ok(['version' => [
            'id' => (int) $version['id'],
            'version_no' => (int) $version['version_no'],
            'title' => $version['title'],
            'content' => $version['content'] ?? '',
            'created_at' => $version['created_at'],
        ]]);
    }

    /** GET /api/v1/documents/{id}/versions/{versionId}/diff - فروقات كلمة-بكلمة مع النسخة التالية. */
    public function versionDiff(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);

        $version = Database::first(
            'SELECT v.*, u.name AS saved_by_name FROM documents_versions v
              LEFT JOIN users u ON u.id = v.saved_by
             WHERE v.id = :id AND v.document_id = :d LIMIT 1',
            ['id' => (int) $params['versionId'], 'd' => $document['id']]
        );
        if (!$version) {
            Api::error('النسخة غير موجودة.', 404, 'not_found');
        }

        $next = Database::first(
            'SELECT * FROM documents_versions WHERE document_id = :d AND version_no > :n ORDER BY version_no LIMIT 1',
            ['d' => $document['id'], 'n' => $version['version_no']]
        );
        $afterContent = $next ? ($next['content'] ?? '') : ($document['content'] ?? '');

        $diff = $this->wordDiff(
            trim(strip_tags((string) ($version['content'] ?? ''))),
            trim(strip_tags((string) $afterContent))
        );

        Api::ok([
            'version_no' => (int) $version['version_no'],
            'saved_by_name' => $version['saved_by_name'] ?? null,
            'created_at' => $version['created_at'],
            'compared_with' => $next ? 'النسخة #' . $next['version_no'] : 'النسخة الحالية',
            // كل عنصر: {kind: same|del|ins, word}
            'diff' => array_map(fn ($d) => ['kind' => $d[0], 'word' => $d[1]], $diff),
        ]);
    }

    /** POST /api/v1/documents/{id}/versions/{versionId}/restore */
    public function restoreVersion(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditDocument($document)) {
            Api::error('لا يمكنك التعديل على هذا المستند.', 403, 'forbidden');
        }

        $version = DocumentVersion::find((int) $params['versionId']);
        if (!$version || (int) $version['document_id'] !== (int) $document['id']) {
            Api::error('الإصدار غير موجود.', 404, 'not_found');
        }

        DocumentVersion::snapshot($document, Auth::id());
        Document::update((int) $document['id'], [
            'title' => $version['title'],
            'content' => $version['content'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        DocumentLog::add((int) $document['id'], Auth::id(), 'updated', 'استعادة إصدار سابق #' . $version['version_no'] . ' من الجوال');
        ActivityLog::log('documents.restore_version', 'document', (string) $document['id'], 'استعادة إصدار مستند');

        Api::ok(['message' => 'تمت استعادة الإصدار #' . $version['version_no'] . '.']);
    }

    // ---------- نفس قواعد DocumentController الويب ----------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            Api::error('حسابك غير مرتبط بشركة.', 422, 'no_company');
        }
        return $companyId;
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('documents.manage');
    }

    private function canEditDocument(array $document): bool
    {
        if (in_array($document['status'], ['signed', 'archived'], true)) {
            return false;
        }
        if ($this->canManage() || (int) $document['created_by'] === Auth::id()) {
            return true;
        }
        return Document::shareRole((int) $document['id'], Auth::id()) === 'editor';
    }

    private function canDeleteDocument(array $document): bool
    {
        if ($this->canManage()) {
            return true;
        }
        if (!in_array($document['status'], ['draft', 'pending_approval'], true)) {
            return false;
        }
        return Permission::check('documents.delete') && (int) $document['created_by'] === Auth::id();
    }

    private function findVisible(int $id, int $companyId): array
    {
        $document = Document::find($id);
        if (!$document || (int) $document['company_id'] !== $companyId) {
            Api::error('المستند غير موجود.', 404, 'not_found');
        }
        Api::requirePermission('documents.view');

        $visible = $this->canManage()
            || (int) $document['created_by'] === Auth::id()
            || Document::shareRole($id, Auth::id()) !== null
            || $document['visibility'] === 'public';
        if (!$visible) {
            Api::error('لا تملك صلاحية عرض هذا المستند.', 403, 'forbidden');
        }

        return $document;
    }

    /** نفس عقد validated() في الويب - المحتوى يمر دائماً عبر HtmlSanitizer. */
    private function validated(int $companyId): array
    {
        $title = trim((string) Api::input('title', ''));
        if ($title === '') {
            Api::error('عنوان المستند مطلوب.', 422, 'validation');
        }

        $type = (string) Api::input('type', 'general');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'general';
        }
        $visibility = (string) Api::input('visibility', 'public') === 'private' ? 'private' : 'public';
        $confidentiality = (string) Api::input('confidentiality', 'normal');
        if (!in_array($confidentiality, self::CONFIDENTIALITIES, true)) {
            $confidentiality = 'normal';
        }

        $templateId = (int) Api::input('template_id', 0) ?: null;
        if ($templateId && !DocumentTemplate::findUsableBy($templateId, Auth::id(), $companyId)) {
            Api::error('القالب المختار غير صالح.', 422, 'validation');
        }

        // أدوات الكتابة المختارة: ما لا يحق للكاتب استخدامه يُهمَل بصمت (كما في الويب).
        $signatureId = (int) Api::input('signature_id', 0) ?: null;
        if ($signatureId && !UserSignature::findUsableBy($signatureId, Auth::id())) {
            $signatureId = null;
        }
        $stampId = (int) Api::input('stamp_id', 0) ?: null;
        if ($stampId && !CompanyStamp::findUsableBy($stampId, Auth::id(), $companyId, $this->canManage())) {
            $stampId = null;
        }

        $followUp = trim((string) Api::input('follow_up_date', ''));
        $expiry = trim((string) Api::input('expiry_date', ''));

        return [
            'title' => mb_substr($title, 0, 255),
            'type' => $type,
            'visibility' => $visibility,
            'confidentiality' => $confidentiality,
            'template_id' => $templateId,
            'signature_id' => $signatureId,
            'stamp_id' => $stampId,
            'signer_title' => mb_substr(trim((string) Api::input('signer_title', '')), 0, 150) ?: null,
            'signer_name' => mb_substr(trim((string) Api::input('signer_name', '')), 0, 150) ?: null,
            'follow_up_date' => $followUp !== '' ? $followUp : null,
            'expiry_date' => $expiry !== '' ? $expiry : null,
            'content' => HtmlSanitizer::sanitize((string) Api::input('content', '')),
        ];
    }

    /**
     * عنصر اختيار (قالب/ختم/توقيع): owner_name يأتي غير فارغ للعناصر المشارَكة
     * فقط - نعرضه للتطبيق ليكتب «مشاركة من فلان» بجانب الاسم.
     */
    private function pickerPayload(array $item): array
    {
        return [
            'id' => (int) $item['id'],
            'name' => $item['name'],
            'owner_name' => $item['owner_name'] ?? null,
        ];
    }

    private function comments(int $documentId): array
    {
        $rows = Database::select(
            'SELECT c.*, u.name AS user_name FROM documents_comments c
              LEFT JOIN users u ON u.id = c.user_id
             WHERE c.document_id = :d ORDER BY c.id',
            ['d' => $documentId]
        );
        return array_map(fn ($c) => [
            'id' => (int) $c['id'],
            'user_name' => $c['user_name'] ?? '',
            'body' => $c['body'],
            'created_at' => $c['created_at'],
        ], $rows);
    }

    private function summaryPayload(array $d): array
    {
        return [
            'id' => (int) $d['id'],
            'number' => $d['number'] ?? null,
            'title' => $d['title'],
            'type' => $d['type'],
            'status' => $d['status'],
            'visibility' => $d['visibility'],
            'confidentiality' => $d['confidentiality'],
            'creator_name' => $d['creator_name'] ?? null,
            'created_at' => $d['created_at'],
        ];
    }

    private function detailPayload(array $d): array
    {
        $creator = Database::first('SELECT name FROM users WHERE id = :id', ['id' => $d['created_by']]);
        $signer = !empty($d['signed_by'])
            ? Database::first('SELECT name FROM users WHERE id = :id', ['id' => $d['signed_by']])
            : null;

        return $this->summaryPayload($d) + [
            'content' => $d['content'] ?? '',
            'creator_name' => $creator['name'] ?? null,
            'created_by' => (int) $d['created_by'],
            'signed_by_name' => $signer['name'] ?? null,
            'signed_at' => $d['signed_at'] ?? null,
            'follow_up_date' => $d['follow_up_date'] ?? null,
            'expiry_date' => $d['expiry_date'] ?? null,
            'template_id' => isset($d['template_id']) && $d['template_id'] ? (int) $d['template_id'] : null,
            // أدوات الكتابة المختارة - تملأ بها شاشة التعديل حقولها مسبقاً.
            'signature_id' => isset($d['signature_id']) && $d['signature_id'] ? (int) $d['signature_id'] : null,
            'stamp_id' => isset($d['stamp_id']) && $d['stamp_id'] ? (int) $d['stamp_id'] : null,
            'signer_title' => $d['signer_title'] ?? null,
            'signer_name' => $d['signer_name'] ?? null,
            'verify_url' => !empty($d['verify_token']) ? base_url('documents/verify/' . $d['verify_token']) : null,
            'updated_at' => $d['updated_at'] ?? null,
        ];
    }

    /**
     * نفس خوارزمية الويب: فروقات كلمة-بكلمة عبر LCS مع سقف 1500 كلمة لكل جهة.
     * @return array<array{0: string, 1: string}>
     */
    private function wordDiff(string $old, string $new): array
    {
        $oldWords = $old === '' ? [] : preg_split('/\s+/u', $old);
        $newWords = $new === '' ? [] : preg_split('/\s+/u', $new);
        $oldWords = array_slice($oldWords, 0, 1500);
        $newWords = array_slice($newWords, 0, 1500);

        $n = count($oldWords);
        $m = count($newWords);
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $oldWords[$i] === $newWords[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $result = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($oldWords[$i] === $newWords[$j]) {
                $result[] = ['same', $oldWords[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $result[] = ['del', $oldWords[$i]];
                $i++;
            } else {
                $result[] = ['ins', $newWords[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $result[] = ['del', $oldWords[$i++]];
        }
        while ($j < $m) {
            $result[] = ['ins', $newWords[$j++]];
        }

        return $result;
    }
}

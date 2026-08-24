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

        $scope = (string) Request::query('scope', 'mine');
        if (!in_array($scope, ['mine', 'shared', 'company'], true)) {
            $scope = 'mine';
        }

        $filters = $this->currentFilters();
        $page = max(1, (int) Request::query('page', 1));
        $result = Document::paginate($companyId, $scope, $this->canManage(), Auth::id(), $page, 15, $filters);

        View::render('documents::index', [
            'pageTitle' => 'المستندات',
            'documents' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 15,
            'scope' => $scope,
            'sharedCount' => Document::countSharedWith($companyId, Auth::id()),
            'filters' => $filters,
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'canManage' => $this->canManage(),
            'canCreate' => $this->can('documents.create'),
        ]);
    }

    /** مشاركة المستند مع موظف بدور (مشاهدة/تعديل) - للمالك والمدير فقط. */
    public function share(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/documents/' . $document['id']);

        if ((int) $document['created_by'] !== Auth::id() && !$this->canManage()) {
            $this->forbidden();
            return;
        }

        $userId = (int) Request::input('user_id', 0);
        $target = Database::first(
            "SELECT id, name FROM users WHERE id = :id AND company_id = :c AND status = 'active'",
            ['id' => $userId, 'c' => $companyId]
        );
        if (!$target || $userId === (int) $document['created_by']) {
            flash_set('error', 'اختر موظفاً صحيحاً (غير مالك المستند).');
            redirect('/documents/' . $document['id']);
        }
        $role = Request::input('role', 'viewer') === 'editor' ? 'editor' : 'viewer';

        Document::setShare((int) $document['id'], $userId, $role, Auth::id());
        DocumentLog::add($document['id'], Auth::id(), 'shared', 'مشاركة مع ' . $target['name'] . ' (' . ($role === 'editor' ? 'تعديل' : 'مشاهدة') . ')');
        Notification::send($userId, '📄 شُورك معك مستند', $document['title'] . ' — ' . ($role === 'editor' ? 'يمكنك التعديل' : 'للمشاهدة'), route('/documents/' . $document['id']));
        flash_set('success', 'شُورك المستند مع ' . $target['name'] . '.');
        redirect('/documents/' . $document['id']);
    }

    public function unshare(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/documents/' . $document['id']);

        if ((int) $document['created_by'] !== Auth::id() && !$this->canManage()) {
            $this->forbidden();
            return;
        }
        Document::removeShare((int) $document['id'], (int) $params['userId']);
        DocumentLog::add($document['id'], Auth::id(), 'unshared', 'إلغاء مشاركة مستخدم');
        flash_set('success', 'أُلغيت المشاركة.');
        redirect('/documents/' . $document['id']);
    }

    /** فرق نسخة عن سابقتها: يُظهر ماذا غيّر صاحب كل حفظ بالضبط. */
    public function versionDiff(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        $version = Database::first(
            'SELECT v.*, u.name AS saved_by_name FROM documents_versions v LEFT JOIN users u ON u.id = v.saved_by WHERE v.id = :id AND v.document_id = :d',
            ['id' => (int) $params['versionId'], 'd' => $document['id']]
        );
        if (!$version) {
            flash_set('error', 'النسخة غير موجودة.');
            redirect('/documents/' . $document['id']);
        }
        // كل لقطة تحمل المحتوى "قبل" حفظِ saved_by - فتعديلُه هو الفرق بين هذه
        // اللقطة والتي تليها (أو محتوى المستند الحالي إن كانت الأحدث).
        $next = Database::first(
            'SELECT * FROM documents_versions WHERE document_id = :d AND version_no > :n ORDER BY version_no ASC LIMIT 1',
            ['d' => $document['id'], 'n' => $version['version_no']]
        );
        $afterContent = $next ? (string) $next['content'] : (string) $document['content'];

        View::render('documents::diff', [
            'pageTitle' => 'تغييرات الإصدار ' . $version['version_no'],
            'document' => $document,
            'version' => $version,
            'next' => $next,
            'diff' => self::wordDiff(
                trim(strip_tags((string) $version['content'])),
                trim(strip_tags($afterContent))
            ),
        ]);
    }

    /**
     * فرق كلمات بسيط (LCS): يُعيد قائمة [نوع، نص] حيث النوع same/del/ins -
     * كافٍ لإظهار "ماذا تغيّر" دون مكتبات خارجية. يُقصّ للنصوص الضخمة.
     */
    private static function wordDiff(string $old, string $new): array
    {
        $a = preg_split('/\s+/u', $old, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('/\s+/u', $new, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $a = array_slice($a, 0, 1500);
        $b = array_slice($b, 0, 1500);
        $n = count($a);
        $m = count($b);

        // مصفوفة LCS
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $out = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out[] = ['same', $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $out[] = ['del', $a[$i]];
                $i++;
            } else {
                $out[] = ['ins', $b[$j]];
                $j++;
            }
        }
        for (; $i < $n; $i++) {
            $out[] = ['del', $a[$i]];
        }
        for (; $j < $m; $j++) {
            $out[] = ['ins', $b[$j]];
        }
        return $out;
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

        // كل مستند يبدأ مسودة للكتابة (التعاونية) - والرقم الرسمي يُمنح فقط
        // عند «الإصدار الرسمي (توقيع)»، عاماً كان أو خاصاً.
        $data['status'] = 'draft';

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
            'canSign' => $this->can('documents.sign'),
            'canDuplicate' => $this->can('documents.create') || $this->canManage(),
            'isOwner' => (int) $document['created_by'] === Auth::id(),
            'myShareRole' => Document::shareRole((int) $document['id'], Auth::id()),
            'shares' => Document::shares((int) $document['id']),
            'companyUsers' => Database::select(
                "SELECT id, name FROM users WHERE company_id = :c AND status = 'active' ORDER BY name",
                ['c' => $companyId]
            ),
            'mySignatures' => \App\Core\UserSignature::usableBy(Auth::id(), $companyId),
            'comments' => Database::select(
                'SELECT c.*, u.name AS user_name FROM documents_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.document_id = :d ORDER BY c.id',
                ['d' => $document['id']]
            ),
        ]);
    }

    /** إضافة تعليق على المستند + تنبيه المنشئ والمشاركين السابقين بالنقاش. */
    public function comment(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $document = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/documents/' . $document['id']);

        $body = trim((string) Request::input('body', ''));
        if ($body === '') {
            flash_set('error', 'اكتب نص التعليق.');
            redirect('/documents/' . $document['id']);
        }

        Database::insert('documents_comments', [
            'document_id' => $document['id'],
            'user_id' => Auth::id(),
            'body' => mb_substr($body, 0, 2000),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // تنبيه المنشئ + كل من علّق سابقاً (عدا كاتب التعليق نفسه)
        $notify = [(int) $document['created_by'] => true];
        foreach (Database::select('SELECT DISTINCT user_id FROM documents_comments WHERE document_id = :d', ['d' => $document['id']]) as $c) {
            $notify[(int) $c['user_id']] = true;
        }
        unset($notify[Auth::id()]);
        foreach (array_keys($notify) as $uid) {
            Notification::send($uid, '💬 تعليق جديد على مستند', $document['title'], route('/documents/' . $document['id']));
        }

        DocumentLog::add($document['id'], Auth::id(), 'commented', 'إضافة تعليق');
        flash_set('success', 'أُضيف تعليقك.');
        redirect('/documents/' . $document['id']);
    }

    /**
     * نسخ المستند كمسودة جديدة: المسار الصحيح "لتعديل" مستند معتمد/موقّع دون كسر
     * أصالته - تُنسخ البيانات والمحتوى، وتُصفَّر الحالة والرقم والاعتمادات والتوقيع،
     * وتأخذ النسخة رمز تحقق جديداً وتمر بدورة الاعتماد من أولها.
     */
    public function duplicate(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('documents.create') && !$this->canManage()) {
            $this->forbidden();
            return;
        }
        $document = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/documents/' . $document['id']);

        $newId = Database::insert('documents_documents', [
            'company_id' => $companyId,
            'type' => $document['type'],
            'visibility' => $document['visibility'],
            'confidentiality' => $document['confidentiality'] ?? 'normal',
            'status' => 'draft',
            'title' => mb_substr($document['title'], 0, 240) . ' (نسخة)',
            'content' => $document['content'],
            'template_id' => $document['template_id'],
            'verify_token' => bin2hex(random_bytes(16)),
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        DocumentLog::add($newId, Auth::id(), 'created', 'أُنشئ نسخةً من المستند #' . $document['id'] . ($document['number'] ? ' (رقم ' . $document['number'] . ')' : ''));
        DocumentLog::add($document['id'], Auth::id(), 'duplicated', 'نُسخ المستند كمسودة جديدة #' . $newId);
        ActivityLog::log('documents.duplicate', 'document', $newId, "نسخ مستند: {$document['title']}");

        flash_set('success', 'أُنشئت نسخة مسودة جديدة — عدّلها كما تشاء، وعند إصدارها رسمياً تأخذ رقماً جديداً.');
        redirect('/documents/' . $newId . '/edit');
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

        // لقطة إصدار قبل التعديل إن تغيّر المحتوى أو العنوان (سجل الإصدارات)
        $contentChanged = array_key_exists('content', $data) && (string) $data['content'] !== (string) ($document['content'] ?? '');
        $titleChanged = array_key_exists('title', $data) && (string) $data['title'] !== (string) ($document['title'] ?? '');
        if ($contentChanged || $titleChanged) {
            DocumentVersion::snapshot($document, Auth::id());
        }

        Document::update($document['id'], $data);
        DocumentLog::add($document['id'], Auth::id(), 'updated', 'تم تعديل بيانات المستند');

        // كتابة تعاونية: إن عدّل مشارِكٌ (غير المالك) يصل المالكَ تنبيهٌ فوري بمن
        // عدّل، ورابط سجل الإصدارات يعرض ماذا تغيّر بالضبط
        if ((int) $document['created_by'] !== Auth::id() && ($contentChanged || $titleChanged)) {
            Notification::send(
                (int) $document['created_by'],
                '✏️ عُدّل مستندك',
                Auth::user()['name'] . ' عدّل «' . $document['title'] . '» — افتح سجل الإصدارات لرؤية التغييرات.',
                route('/documents/' . $document['id'])
            );
        }

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
            case 'sign':
                // الفلسفة الجديدة: لا دورة اعتماد - المالك يُصدر مستنده رسمياً مباشرة
                // (توقيع + ختم + رقم رسمي)، وكذلك من يملك صلاحية التوقيع أو المدير.
                if (!in_array($document['status'], ['draft', 'pending_approval', 'approved'], true)) {
                    flash_set('error', 'هذا المستند ليس جاهزاً للإصدار الرسمي.');
                    redirect('/documents/' . $document['id']);
                }
                if ((int) $document['created_by'] !== Auth::id() && !$this->canManage() && !$this->can('documents.sign')) {
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
                // الرقم الرسمي يُمنح عند الإصدار (كان يُمنح في الاعتماد الملغى)
                if (empty($document['number'])) {
                    $signUpdate['number'] = DocumentSetting::generateNumber($companyId);
                }
                $sigId = (int) Request::input('signature_id', 0);
                if ($sigId) {
                    $sig = \App\Core\UserSignature::findUsableBy($sigId, Auth::id());
                    if ($sig) {
                        $signUpdate['signature_file'] = $sig['image'];
                    }
                }
                Document::update($document['id'], $signUpdate);
                $officialNumber = $signUpdate['number'] ?? $document['number'];
                DocumentLog::add($document['id'], Auth::id(), 'signed', 'إصدار رسمي وتوقيع' . ($officialNumber ? ' — رقم ' . $officialNumber : ''));
                if (!$isCreator) {
                    Notification::send((int) $document['created_by'], '🔏 صدر مستندك رسمياً', $document['title'] . ($officialNumber ? ' — رقم ' . $officialNumber : ''), route('/documents/' . $document['id']));
                }
                flash_set('success', 'صدر المستند رسمياً' . ($officialNumber ? ' برقم ' . $officialNumber : '') . ' وتم توقيعه.');
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

        // ورقة المستند تُعرض لحامل الرمز فقط عندما يكون تصنيفه "عادي" - المستندات
        // الداخلية/السرية تبقى بيانات تأكيد فقط دون محتوى.
        $showPaper = $document && ($document['confidentiality'] ?? 'normal') === 'normal';

        View::render('documents::verify', [
            'pageTitle' => 'التحقق من مستند',
            'document' => $document,
            'typeLabels' => Document::typeLabels(),
            'statusLabels' => Document::statusLabels(),
            'paperUrl' => $showPaper ? base_url('documents/verify/' . $token . '/view?embed=1') : null,
        ], '');
    }

    /**
     * عرض ورقة المستند لحامل رمز التحقق (بلا مصادقة) - للتصنيف "عادي" فقط.
     * الصور تُضمَّن data URI لأن مسارات /media المحمية لا تعمل لزائر غير مسجّل.
     */
    public function verifyView(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $document = ctype_xdigit($token) ? Document::findByToken($token) : null;
        if (!$document || ($document['confidentiality'] ?? 'normal') !== 'normal') {
            http_response_code(404);
            exit;
        }
        $companyId = (int) $document['company_id'];

        $template = $document['template_id'] ? DocumentTemplate::find((int) $document['template_id']) : null;
        if ($template && (int) $template['company_id'] !== $companyId) {
            $template = null;
        }
        $settings = DocumentSetting::getOrCreate($companyId);

        $signatureUrl = null;
        if (!empty($document['signature_file'])) {
            $signatureUrl = \App\Core\Uploads::dataUri(BASE_PATH . '/storage/uploads/signatures/' . $companyId . '/' . $document['signature_file']);
        } elseif (!empty($settings['signature_image'])) {
            $signatureUrl = \App\Core\Uploads::dataUri(BASE_PATH . '/storage/uploads/documents/' . $companyId . '/' . $settings['signature_image']);
        }

        $stampUrl = null;
        if ($template && !empty($template['stamp_id'])) {
            $stamp = \App\Core\CompanyStamp::findForCompany((int) $template['stamp_id'], $companyId);
            if ($stamp) {
                $stampUrl = \App\Core\Uploads::dataUri(BASE_PATH . '/storage/uploads/stamps/' . $companyId . '/' . $stamp['image']);
            }
        }
        if (!$stampUrl && !empty($settings['stamp_image'])) {
            $stampUrl = \App\Core\Uploads::dataUri(BASE_PATH . '/storage/uploads/documents/' . $companyId . '/' . $settings['stamp_image']);
        }

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
            'company' => Database::first('SELECT * FROM companies WHERE id = :id', ['id' => $companyId]),
            'signatureUrl' => $signatureUrl,
            'stampUrl' => $stampUrl,
            'signerName' => $signerName,
            'verifyUrl' => base_url('documents/verify/' . $token),
            'bgUrl' => ($template && !empty($template['background_image']))
                ? \App\Core\Uploads::dataUri(BASE_PATH . '/storage/uploads/documents/' . $companyId . '/' . $template['background_image'])
                : null,
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

    /**
     * فلسفة الكتابة التعاونية: التعديل متاح للمالك ولمن شُورك معه بدور «تعديل»
     * وللمدير. يُقفل التعديل فقط بعد الإصدار الرسمي (الموقّع) وللمؤرشف -
     * حفاظاً على أصالة ما صدر برقم رسمي؛ وللتعديل تُنسخ مسودة جديدة.
     */
    private function canEditDocument(array $document): bool
    {
        if (in_array($document['status'], ['signed', 'archived'], true)) {
            return false;
        }
        if ($this->canManage()) {
            return true;
        }
        if ((int) $document['created_by'] === Auth::id()) {
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

        // الرؤية: المدير، أو المالك، أو من شُورك معه المستند، أو أي موظف إن كان عاماً
        $visible = $this->canManage()
            || (int) $document['created_by'] === Auth::id()
            || Document::shareRole((int) $document['id'], Auth::id()) !== null
            || $document['visibility'] === 'public';
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

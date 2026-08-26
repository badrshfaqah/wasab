<?php

namespace Modules\Archive\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use Modules\Archive\Models\ArchiveCategory;
use Modules\Archive\Models\ArchiveFile;
use Modules\Archive\Models\ArchiveFileDownload;
use Modules\Archive\Models\ArchiveFileLog;
use Modules\Archive\Models\ArchiveFileShare;
use Modules\Archive\Models\ArchiveFileVersion;
use Modules\Archive\Models\ArchiveSetting;
use Modules\Archive\Models\ArchiveTag;

class ArchiveFileController
{
    private const VISIBILITY = ['inherit', 'all', 'specific_users', 'system_admin_only', 'company_admin_only'];
    private const STORAGE_DIR = '/storage/uploads/archive';

    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('archive.view')) {
            $this->forbidden();
            return;
        }
        ArchiveFile::markExpiredPastDue($companyId);

        $filters = $this->currentFilters();
        $page = max(1, (int) Request::query('page', 1));
        $view = Request::query('view', 'list') === 'grid' ? 'grid' : 'list';

        $result = ArchiveFile::paginate($companyId, Auth::id(), Auth::isSystemAdmin(), $this->canManage(), Auth::isCompanyAdmin(), $filters, $page, 24);

        View::render('archive::index', [
            'pageTitle' => 'أرشيف الملفات',
            'files' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 24,
            'filters' => $filters,
            'view' => $view,
            'categories' => ArchiveCategory::tree($companyId),
            'tags' => ArchiveTag::forCompany($companyId),
            'canCreate' => $this->can('archive.create'),
            'canManage' => $this->canManage(),
            'canShare' => $this->can('archive.share'),
            'canDownload' => $this->can('archive.download'),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('archive.create')) {
            $this->forbidden();
            return;
        }

        View::render('archive::form', [
            'pageTitle' => 'رفع ملف',
            'file' => null,
            'categories' => ArchiveCategory::tree($companyId),
            'companyUsers' => $this->companyUsers($companyId),
            'accessUserIds' => [],
            'allTags' => ArchiveTag::forCompany($companyId),
            'fileTags' => [],
            'linkables' => $this->linkableEntities($companyId),
            'canShare' => $this->can('archive.share'),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('archive.create')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/archive/upload');

        $meta = $this->validatedMeta($companyId);
        if ($meta === null) {
            redirect('/archive/upload');
        }

        $upload = Uploads::handleFile('file', BASE_PATH . self::STORAGE_DIR . '/' . $companyId, ArchiveFile::allowedExtensions(), ArchiveFile::MAX_BYTES);
        if ($upload['error']) {
            flash_set('error', $upload['error']);
            redirect('/archive/upload');
        }
        if (!$upload['filename']) {
            flash_set('error', 'يرجى اختيار ملف للرفع.');
            redirect('/archive/upload');
        }

        $data = array_merge($meta, [
            'company_id' => $companyId,
            'original_name' => $upload['original'],
            'stored_name' => $upload['filename'],
            'extension' => $upload['extension'],
            'mime_type' => 'application/octet-stream',
            'size' => $upload['size'],
            'created_by' => Auth::id(),
        ]);

        $fileId = ArchiveFile::create($data);
        if ($meta['visibility_type'] === 'specific_users') {
            ArchiveFile::setAccessUsers($fileId, (array) Request::input('access_users', []));
        }
        $this->syncTagsFromRequest($companyId, $fileId);
        $this->syncLinksFromRequest($companyId, $fileId);

        ArchiveFileLog::add($fileId, Auth::id(), 'uploaded', 'تم رفع الملف');
        ActivityLog::log('archive.upload', 'archive_file', $fileId, "رفع ملف: {$upload['original']}");

        // مشاركة مباشرة: إنشاء رابط مشاركة فور الرفع (إن طُلب وللمصرّح لهم)
        $shared = false;
        if (Request::input('create_share') && $this->can('archive.share')) {
            $days = (int) Request::input('share_expires_in_days', 7);
            if ($days < 1 || $days > 90) {
                $days = 7;
            }
            $maxDownloads = (int) Request::input('share_max_downloads', 0) ?: null;
            ArchiveFileShare::create($fileId, Auth::id(), date('Y-m-d H:i:s', strtotime("+{$days} days")), $maxDownloads);
            ArchiveFileLog::add($fileId, Auth::id(), 'shared', 'تم إنشاء رابط مشاركة مباشر عند الرفع');
            $shared = true;
        }

        flash_set('success', $shared ? 'تم رفع الملف وإنشاء رابط المشاركة — انسخه من الأسفل.' : 'تم رفع الملف بنجاح.');
        redirect('/archive/' . $fileId);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file, $category] = $this->findVisible((int) $params['id'], $companyId);

        ArchiveFile::incrementView($file['id']);
        ArchiveFileLog::add($file['id'], Auth::id(), 'viewed', 'تمت مشاهدة الملف');
        ActivityLog::log('archive.view', 'archive_file', $file['id'], "مشاهدة ملف: {$file['original_name']}");

        View::render('archive::show', [
            'pageTitle' => $file['title'] ?: $file['original_name'],
            'file' => $file,
            'category' => $category,
            'logs' => ArchiveFileLog::forFile($file['id']),
            'downloads' => ArchiveFileDownload::forFile($file['id']),
            'versions' => ArchiveFileVersion::forFile($file['id']),
            'tags' => ArchiveTag::forFile($file['id']),
            'shares' => ArchiveFileShare::forFile($file['id']),
            'links' => ArchiveFile::links($file['id']),
            'categories' => ArchiveCategory::tree($companyId),
            'expiryWarningDays' => (int) ArchiveSetting::getOrCreate($companyId)['expiry_warning_days'],
            'canEdit' => $this->can('archive.edit'),
            'canDelete' => $this->can('archive.delete'),
            'canDownload' => $this->can('archive.download'),
            'canShare' => $this->can('archive.share'),
            'canManage' => $this->canManage(),
        ]);
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('archive.edit')) {
            $this->forbidden();
            return;
        }

        View::render('archive::form', [
            'pageTitle' => 'تعديل بيانات ملف',
            'file' => $file,
            'categories' => ArchiveCategory::tree($companyId),
            'companyUsers' => $this->companyUsers($companyId),
            'accessUserIds' => ArchiveFile::accessUserIds($file['id']),
            'allTags' => ArchiveTag::forCompany($companyId),
            'fileTags' => ArchiveTag::forFile($file['id']),
            'linkables' => $this->linkableEntities($companyId),
            'currentLinks' => array_map(fn ($l) => $l['linked_module'] . ':' . $l['linked_id'], ArchiveFile::links($file['id'])),
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('archive.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/archive/' . $file['id'] . '/edit');

        $meta = $this->validatedMeta($companyId);
        if ($meta === null) {
            redirect('/archive/' . $file['id'] . '/edit');
        }
        $meta['updated_by'] = Auth::id();
        $meta['updated_at'] = date('Y-m-d H:i:s');

        $categoryChanged = (int) $file['category_id'] !== (int) $meta['category_id'];

        ArchiveFile::update($file['id'], $meta);
        ArchiveFile::setAccessUsers(
            $file['id'],
            $meta['visibility_type'] === 'specific_users' ? (array) Request::input('access_users', []) : []
        );
        $this->syncTagsFromRequest($companyId, $file['id']);
        $this->syncLinksFromRequest($companyId, $file['id']);

        ArchiveFileLog::add($file['id'], Auth::id(), $categoryChanged ? 'category_changed' : 'updated', $categoryChanged ? 'تم تغيير تصنيف الملف' : 'تم تعديل بيانات الملف');
        ActivityLog::log('archive.update', 'archive_file', $file['id'], "تعديل ملف: {$file['original_name']}");
        flash_set('success', 'تم حفظ التعديلات.');
        redirect('/archive/' . $file['id']);
    }

    public function move(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('archive.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/archive/' . $file['id']);

        $categoryId = (int) Request::input('category_id', 0);
        $category = $categoryId ? ArchiveCategory::find($categoryId) : null;
        if (!$category || (int) $category['company_id'] !== $companyId) {
            flash_set('error', 'التصنيف المختار غير صالح.');
            redirect('/archive/' . $file['id']);
        }

        ArchiveFile::update($file['id'], ['category_id' => $categoryId, 'updated_by' => Auth::id(), 'updated_at' => date('Y-m-d H:i:s')]);
        ArchiveFileLog::add($file['id'], Auth::id(), 'moved', "تم نقل الملف إلى تصنيف: {$category['name']}");
        ActivityLog::log('archive.move', 'archive_file', $file['id'], "نقل ملف: {$file['original_name']}");
        flash_set('success', 'تم نقل الملف.');
        redirect('/archive/' . $file['id']);
    }

    public function replace(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('archive.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/archive/' . $file['id']);

        $upload = Uploads::handleFile('file', BASE_PATH . self::STORAGE_DIR . '/' . $companyId, ArchiveFile::allowedExtensions(), ArchiveFile::MAX_BYTES);
        if ($upload['error']) {
            flash_set('error', $upload['error']);
            redirect('/archive/' . $file['id']);
        }
        if (!$upload['filename']) {
            flash_set('error', 'يرجى اختيار الملف الجديد.');
            redirect('/archive/' . $file['id']);
        }

        ArchiveFileVersion::add((int) $file['id'], (int) $file['version'], $file['stored_name'], $file['original_name'], (int) $file['size'], Auth::id());

        $newVersion = (int) $file['version'] + 1;
        ArchiveFile::update($file['id'], [
            'stored_name' => $upload['filename'],
            'original_name' => $upload['original'],
            'extension' => $upload['extension'],
            'size' => $upload['size'],
            'version' => $newVersion,
            'updated_by' => Auth::id(),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ArchiveFileLog::add($file['id'], Auth::id(), 'version_replaced', "تم رفع إصدار جديد (v{$newVersion})");
        ActivityLog::log('archive.update', 'archive_file', $file['id'], "إصدار جديد لملف: {$upload['original']}");
        flash_set('success', 'تم رفع إصدار جديد من الملف.');
        redirect('/archive/' . $file['id']);
    }

    public function statusAction(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canManage() && !$this->can('archive.edit')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/archive/' . $file['id']);

        $action = Request::input('action');
        switch ($action) {
            case 'renew':
                $expiresAt = Request::input('expires_at') ?: null;
                if (!$expiresAt) {
                    flash_set('error', 'يرجى تحديد تاريخ الصلاحية الجديد.');
                    redirect('/archive/' . $file['id']);
                }
                ArchiveFile::update($file['id'], ['status' => 'active', 'expires_at' => $expiresAt, 'updated_by' => Auth::id(), 'updated_at' => date('Y-m-d H:i:s')]);
                ArchiveFileLog::add($file['id'], Auth::id(), 'expiry_changed', "تم تجديد تاريخ الصلاحية إلى {$expiresAt}");
                flash_set('success', 'تم تجديد تاريخ الصلاحية.');
                break;

            case 'close':
                ArchiveFile::update($file['id'], ['status' => 'closed', 'updated_by' => Auth::id(), 'updated_at' => date('Y-m-d H:i:s')]);
                ArchiveFileLog::add($file['id'], Auth::id(), 'closed', 'تم إغلاق الملف');
                flash_set('success', 'تم إغلاق الملف.');
                break;

            case 'archive':
                ArchiveFile::update($file['id'], ['status' => 'archived', 'updated_by' => Auth::id(), 'updated_at' => date('Y-m-d H:i:s')]);
                ArchiveFileLog::add($file['id'], Auth::id(), 'archived', 'تمت أرشفة الملف');
                flash_set('success', 'تمت أرشفة الملف.');
                break;

            default:
                flash_set('error', 'إجراء غير معروف.');
        }

        redirect('/archive/' . $file['id']);
    }

    /** حذف ناعم: ينقل الملف لسلة المحذوفات. الملف على القرص والصف يبقيان حتى الاستعادة أو الحذف النهائي. */
    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('archive.delete')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/archive/' . $file['id']);

        ArchiveFile::softDelete($file['id'], Auth::id());
        ArchiveFileLog::add($file['id'], Auth::id(), 'trashed', 'تم نقل الملف إلى سلة المحذوفات');
        ActivityLog::log('archive.delete', 'archive_file', $file['id'], "نقل ملف لسلة المحذوفات: {$file['original_name']}");
        flash_set('success', 'تم نقل الملف إلى سلة المحذوفات.');
        redirect('/archive');
    }

    public function trash(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage() && !$this->can('archive.delete')) {
            $this->forbidden();
            return;
        }

        View::render('archive::trash', [
            'pageTitle' => 'سلة المحذوفات',
            'files' => ArchiveFile::trashed($companyId),
        ]);
    }

    public function restore(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage() && !$this->can('archive.delete')) {
            $this->forbidden();
            return;
        }
        $file = $this->findTrashed((int) $params['id'], $companyId);
        $this->verifyCsrf('/archive/trash');

        ArchiveFile::restore($file['id'], Auth::id());
        ArchiveFileLog::add($file['id'], Auth::id(), 'restored', 'تمت استعادة الملف من سلة المحذوفات');
        ActivityLog::log('archive.restore', 'archive_file', $file['id'], "استعادة ملف: {$file['original_name']}");
        flash_set('success', 'تمت استعادة الملف.');
        redirect('/archive/trash');
    }

    /** حذف نهائي فعلي من سلة المحذوفات فقط: يحذف الملف من القرص وكل إصداراته السابقة والصف نفسه. */
    public function permanentDelete(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage() && !$this->can('archive.delete')) {
            $this->forbidden();
            return;
        }
        $file = $this->findTrashed((int) $params['id'], $companyId);
        $this->verifyCsrf('/archive/trash');

        foreach (ArchiveFileVersion::forFile($file['id']) as $v) {
            @unlink(BASE_PATH . self::STORAGE_DIR . '/' . $file['company_id'] . '/' . $v['stored_name']);
        }
        @unlink(BASE_PATH . self::STORAGE_DIR . '/' . $file['company_id'] . '/' . $file['stored_name']);

        ArchiveFile::delete($file['id']);
        ActivityLog::log('archive.delete', 'archive_file', $file['id'], "حذف نهائي لملف: {$file['original_name']}");
        flash_set('success', 'تم حذف الملف نهائياً.');
        redirect('/archive/trash');
    }

    public function createShare(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('archive.share')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/archive/' . $file['id']);

        $days = (int) Request::input('expires_in_days', 7);
        if ($days < 1 || $days > 90) {
            $days = 7;
        }
        $maxDownloads = (int) Request::input('max_downloads', 0) ?: null;
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        ArchiveFileShare::create($file['id'], Auth::id(), $expiresAt, $maxDownloads);
        ArchiveFileLog::add($file['id'], Auth::id(), 'shared', 'تم إنشاء رابط مشاركة مؤقت');
        ActivityLog::log('archive.share', 'archive_file', $file['id'], "مشاركة ملف: {$file['original_name']}");
        flash_set('success', 'تم إنشاء رابط المشاركة.');
        redirect('/archive/' . $file['id']);
    }

    public function revokeShare(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('archive.share')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/archive/' . $file['id']);

        $share = ArchiveFileShare::find((int) $params['shareId']);
        if ($share && (int) $share['file_id'] === $file['id']) {
            ArchiveFileShare::revoke($share['id']);
            ArchiveFileLog::add($file['id'], Auth::id(), 'share_revoked', 'تم إلغاء رابط مشاركة');
        }

        flash_set('success', 'تم إلغاء رابط المشاركة.');
        redirect('/archive/' . $file['id']);
    }

    public function download(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('archive.download')) {
            $this->forbidden();
            return;
        }

        $path = BASE_PATH . self::STORAGE_DIR . '/' . $file['company_id'] . '/' . $file['stored_name'];
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        ArchiveFile::incrementDownload($file['id']);
        ArchiveFileDownload::add($file['id'], Auth::id());
        ArchiveFileLog::add($file['id'], Auth::id(), 'downloaded', 'تم تحميل الملف');
        ActivityLog::log('archive.download', 'archive_file', $file['id'], "تحميل ملف: {$file['original_name']}");

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** معاينة داخل الصفحة (PDF/صور فقط) - لا تُسجَّل كتحميل، فقط تُبَث للعرض المباشر. */
    public function preview(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        // المعاينة تبثّ الملف الخام كاملاً، فتخضع لصلاحية التحميل نفسها - وإلا
        // لأمكن حفظ الملف عبر المعاينة لمن يملك المشاهدة فقط دون التحميل.
        if (!$this->can('archive.download')) {
            $this->forbidden();
            return;
        }
        [$file] = $this->findVisible((int) $params['id'], $companyId);

        $path = BASE_PATH . self::STORAGE_DIR . '/' . $file['company_id'] . '/' . $file['stored_name'];
        if (!is_file($path) || (!ArchiveFile::isPdf($file['extension']) && !ArchiveFile::isImage($file['extension']))) {
            http_response_code(404);
            exit;
        }

        $mime = ArchiveFile::isPdf($file['extension']) ? 'application/pdf' : 'image/' . ($file['extension'] === 'jpg' ? 'jpeg' : $file['extension']);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . rawurlencode($file['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('archive::no-company', ['pageTitle' => 'أرشيف الملفات']);
            exit;
        }
        return $companyId;
    }

    private function can(string $key): bool
    {
        return \App\Core\Permission::check($key) || \App\Core\Permission::check('archive.manage');
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || \App\Core\Permission::check('archive.manage');
    }

    /** يعيد [ملف, تصنيفه] بعد التأكد من الشركة الصحيحة وصلاحية المشاهدة الأساسية والفعلية لهذا الملف تحديداً. */
    private function findVisible(int $id, int $companyId): array
    {
        $file = ArchiveFile::find($id);
        if (!$file || (int) $file['company_id'] !== $companyId || $file['deleted_at'] !== null) {
            flash_set('error', 'الملف غير موجود.');
            redirect('/archive');
        }

        $category = ArchiveCategory::find((int) $file['category_id']);

        if (!$this->can('archive.view')) {
            $this->forbidden();
            exit;
        }

        $visible = ArchiveFile::isVisibleTo($file, $category, Auth::id(), Auth::isSystemAdmin(), $this->canManage(), Auth::isCompanyAdmin());
        if (!$visible) {
            $this->forbidden();
            exit;
        }

        return [$file, $category];
    }

    /** يُستخدم فقط من إجراءات سلة المحذوفات (استعادة/حذف نهائي) - يشترط أن يكون الملف فعلاً في السلة. */
    private function findTrashed(int $id, int $companyId): array
    {
        $file = ArchiveFile::find($id);
        if (!$file || (int) $file['company_id'] !== $companyId || $file['deleted_at'] === null) {
            flash_set('error', 'الملف غير موجود في سلة المحذوفات.');
            redirect('/archive/trash');
        }
        return $file;
    }

    private function syncTagsFromRequest(int $companyId, int $fileId): void
    {
        $raw = trim((string) Request::input('tags', ''));
        if ($raw === '') {
            ArchiveTag::syncForFile($fileId, []);
            return;
        }
        $names = array_filter(array_map('trim', explode(',', $raw)));
        $tagIds = ArchiveTag::findOrCreateIds($companyId, $names);
        ArchiveTag::syncForFile($fileId, $tagIds);
    }

    private function companyUsers(int $companyId): array
    {
        return Database::select('SELECT id, name FROM users WHERE company_id = :c AND status = "active" ORDER BY name', ['c' => $companyId]);
    }

    private function currentFilters(): array
    {
        $filters = [];
        if ($categoryId = (int) Request::query('category_id', 0)) {
            $filters['category_id'] = $categoryId;
        }
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        $status = Request::query('status', '');
        if (array_key_exists($status, ArchiveFile::statusLabels())) {
            $filters['status'] = $status;
        }
        if ($extension = Request::query('extension', '')) {
            $filters['extension'] = $extension;
        }
        if ($tagId = (int) Request::query('tag_id', 0)) {
            $filters['tag_id'] = $tagId;
        }
        $sort = Request::query('sort', 'date_desc');
        $filters['sort'] = in_array($sort, \Modules\Archive\Models\ArchiveFile::sortKeys(), true) ? $sort : 'date_desc';
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

    private function validatedMeta(int $companyId): ?array
    {
        $title = trim((string) Request::input('title', ''));
        $description = trim((string) Request::input('description', ''));
        $keywords = trim((string) Request::input('keywords', ''));
        $notes = trim((string) Request::input('notes', ''));
        $categoryId = (int) Request::input('category_id', 0);
        $visibility = Request::input('visibility_type', 'inherit');
        $expiresAt = Request::input('expires_at') ?: null;

        $category = ArchiveCategory::find($categoryId);
        if (!$category || (int) $category['company_id'] !== $companyId) {
            flash_set('error', 'يرجى اختيار تصنيف صالح.');
            return null;
        }
        if (!in_array($visibility, self::VISIBILITY, true)) {
            $visibility = 'inherit';
        }

        return [
            'category_id' => $categoryId,
            'title' => $title ?: null,
            'description' => $description ?: null,
            'keywords' => $keywords ?: null,
            'notes' => $notes ?: null,
            'visibility_type' => $visibility,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * خريطة أنواع الكيانات القابلة للربط: module => [الجدول, عمود الاسم, يتطلب تفعيل إضافة؟].
     * 'users' كيان نواة (أصحاب العضويات) لا يتبع إضافة، فلا يشترط تفعيلاً.
     */
    private function linkableTypes(): array
    {
        return [
            'documents' => ['documents_documents', 'title', true],
            'assets' => ['assets_assets', 'name', true],
            'employees' => ['employees_profiles', 'full_name', true],
            'users' => ['users', 'name', false],
            'clients' => ['clients_clients', 'name', true],
            'crm' => ['crm_organizations', 'name', true],
        ];
    }

    private function typeActive(string $mod, bool $needsModule): bool
    {
        return !$needsModule || \App\Core\ModuleManager::isActive($mod);
    }

    /** يحلّل "module:id" ويتحقق من ملكية الشركة والتفعيل. يُرجع [module,id,label] أو [null,null,null]. */
    private function parseLink(int $companyId, string $raw): array
    {
        if ($raw === '' || !str_contains($raw, ':')) {
            return [null, null, null];
        }
        [$mod, $id] = explode(':', $raw, 2);
        $id = (int) $id;
        $types = $this->linkableTypes();
        if (!isset($types[$mod]) || $id < 1) {
            return [null, null, null];
        }
        [$table, $nameCol, $needsModule] = $types[$mod];
        if (!$this->typeActive($mod, $needsModule)) {
            return [null, null, null];
        }
        $row = Database::first("SELECT `{$nameCol}` AS label FROM `{$table}` WHERE id = :id AND company_id = :c", ['id' => $id, 'c' => $companyId]);
        return $row ? [$mod, $id, mb_substr((string) $row['label'], 0, 200)] : [null, null, null];
    }

    /**
     * الكيانات القابلة للربط مجمّعة حسب عنوان معروض، وكل عنصر يحمل إضافته الفعلية.
     * "الأشخاص" = موظفو الملف الوظيفي + أصحاب العضويات (المستخدمون) - مع منع التكرار:
     * المستخدم المربوط بملف وظيفي يظهر مرة واحدة كموظف فقط.
     */
    private function linkableEntities(int $companyId): array
    {
        $out = [];
        if (\App\Core\ModuleManager::isActive('documents')) {
            $out['مستند'] = $this->rowsFor($companyId, 'documents', 'documents_documents', 'title');
        }
        if (\App\Core\ModuleManager::isActive('assets')) {
            $out['أصل / عهدة'] = $this->rowsFor($companyId, 'assets', 'assets_assets', 'name');
        }
        if (\App\Core\ModuleManager::isActive('clients')) {
            $out['عميل'] = $this->rowsFor($companyId, 'clients', 'clients_clients', 'name');
        }
        if (\App\Core\ModuleManager::isActive('crm')) {
            $out['جهة (CRM)'] = $this->rowsFor($companyId, 'crm', 'crm_organizations', 'name');
        }

        // الأشخاص: موظفون (إن كانت الإضافة مفعّلة) + مستخدمون غير مربوطين بملف وظيفي
        $persons = [];
        $linkedUserIds = [];
        if (\App\Core\ModuleManager::isActive('employees')) {
            foreach (Database::select(
                "SELECT id, full_name, linked_user_id FROM employees_profiles WHERE company_id = :c ORDER BY full_name LIMIT 1000",
                ['c' => $companyId]
            ) as $e) {
                $persons[] = ['module' => 'employees', 'id' => (int) $e['id'], 'label' => $e['full_name']];
                if (!empty($e['linked_user_id'])) {
                    $linkedUserIds[(int) $e['linked_user_id']] = true;
                }
            }
        }
        foreach (Database::select(
            "SELECT id, name, email FROM users WHERE company_id = :c ORDER BY name LIMIT 1000",
            ['c' => $companyId]
        ) as $u) {
            if (isset($linkedUserIds[(int) $u['id']])) {
                continue; // مربوط بملف وظيفي - ظهر كموظف
            }
            $persons[] = ['module' => 'users', 'id' => (int) $u['id'], 'label' => $u['name'] . ' (حساب)'];
        }
        if ($persons) {
            $out['شخص'] = $persons;
        }
        return $out;
    }

    private function rowsFor(int $companyId, string $mod, string $table, string $nameCol): array
    {
        $rows = Database::select(
            "SELECT id, `{$nameCol}` AS label FROM `{$table}` WHERE company_id = :c ORDER BY `{$nameCol}` LIMIT 1000",
            ['c' => $companyId]
        );
        return array_map(fn ($r) => ['module' => $mod, 'id' => (int) $r['id'], 'label' => $r['label']], $rows);
    }

    /** يقرأ روابط النموذج المتعددة (linked[]) ويستبدل روابط الملف. */
    private function syncLinksFromRequest(int $companyId, int $fileId): void
    {
        $raw = (array) ($_POST['linked'] ?? []);
        $links = [];
        foreach ($raw as $value) {
            [$mod, $id, $label] = $this->parseLink($companyId, (string) $value);
            if ($mod !== null) {
                $links[] = ['module' => $mod, 'id' => $id, 'label' => $label];
            }
        }
        ArchiveFile::setLinks($fileId, $links);
    }

    /** مسار عرض الكيان المرتبط (المستخدمون بلا صفحة عرض عامة). */
    public static function linkUrl(?string $mod, $id): ?string
    {
        if (!$mod || !$id) {
            return null;
        }
        $paths = ['documents' => '/documents/', 'assets' => '/custody/', 'employees' => '/employees/', 'clients' => '/clients/'];
        return isset($paths[$mod]) ? route($paths[$mod] . (int) $id) : null;
    }

    /** أيقونة نوع الكيان المرتبط (للعرض). */
    public static function linkIcon(?string $mod): string
    {
        return ['documents' => '📄', 'assets' => '📦', 'employees' => '👤', 'users' => '👤', 'clients' => '👔'][$mod] ?? '🔗';
    }
}

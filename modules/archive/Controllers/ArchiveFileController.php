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
use Modules\Archive\Models\ArchiveFileVersion;
use Modules\Archive\Models\ArchiveSetting;

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

        $result = ArchiveFile::paginate($companyId, Auth::id(), Auth::isSystemAdmin(), $this->canManage(), $filters, $page, 24);

        View::render('archive::index', [
            'pageTitle' => 'أرشيف الملفات',
            'files' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 24,
            'filters' => $filters,
            'view' => $view,
            'categories' => ArchiveCategory::tree($companyId),
            'canCreate' => $this->can('archive.create'),
            'canManage' => $this->canManage(),
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

        $upload = Uploads::handleFile('file', BASE_PATH . self::STORAGE_DIR, ArchiveFile::allowedExtensions(), ArchiveFile::MAX_BYTES);
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

        ArchiveFileLog::add($fileId, Auth::id(), 'uploaded', 'تم رفع الملف');
        ActivityLog::log('archive.upload', 'archive_file', $fileId, "رفع ملف: {$upload['original']}");
        flash_set('success', 'تم رفع الملف بنجاح.');
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
            'categories' => ArchiveCategory::tree($companyId),
            'expiryWarningDays' => (int) ArchiveSetting::getOrCreate($companyId)['expiry_warning_days'],
            'canEdit' => $this->can('archive.edit'),
            'canDelete' => $this->can('archive.delete'),
            'canDownload' => $this->can('archive.download'),
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

        $upload = Uploads::handleFile('file', BASE_PATH . self::STORAGE_DIR, ArchiveFile::allowedExtensions(), ArchiveFile::MAX_BYTES);
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

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('archive.delete')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/archive/' . $file['id']);

        foreach (ArchiveFileVersion::forFile($file['id']) as $v) {
            @unlink(BASE_PATH . self::STORAGE_DIR . '/' . $v['stored_name']);
        }
        @unlink(BASE_PATH . self::STORAGE_DIR . '/' . $file['stored_name']);

        ArchiveFile::delete($file['id']);
        ActivityLog::log('archive.delete', 'archive_file', $file['id'], "حذف ملف: {$file['original_name']}");
        flash_set('success', 'تم حذف الملف.');
        redirect('/archive');
    }

    public function download(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$file] = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->can('archive.download')) {
            $this->forbidden();
            return;
        }

        $path = BASE_PATH . self::STORAGE_DIR . '/' . $file['stored_name'];
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
        [$file] = $this->findVisible((int) $params['id'], $companyId);

        $path = BASE_PATH . self::STORAGE_DIR . '/' . $file['stored_name'];
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
        if (!$file || (int) $file['company_id'] !== $companyId) {
            flash_set('error', 'الملف غير موجود.');
            redirect('/archive');
        }

        $category = ArchiveCategory::find((int) $file['category_id']);

        if (!$this->can('archive.view')) {
            $this->forbidden();
            exit;
        }

        $visible = ArchiveFile::isVisibleTo($file, $category, Auth::id(), Auth::isSystemAdmin(), $this->canManage());
        if (!$visible) {
            $this->forbidden();
            exit;
        }

        return [$file, $category];
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
        $sort = Request::query('sort', 'date_desc');
        $filters['sort'] = in_array($sort, ['name_asc', 'name_desc', 'date_asc', 'date_desc', 'size_asc', 'size_desc', 'modified_desc'], true) ? $sort : 'date_desc';
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
}

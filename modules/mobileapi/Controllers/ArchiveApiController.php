<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Permission;
use App\Core\Uploads;
use Modules\Archive\Models\ArchiveCategory;
use Modules\Archive\Models\ArchiveFile;
use Modules\Archive\Models\ArchiveFileDownload;
use Modules\Archive\Models\ArchiveFileLog;
use Modules\Mobileapi\Support\Api;

/**
 * أرشيف الملفات للجوال: تصفّح بالتصنيفات والبحث، معاينة وتنزيل، ورفع مباشر
 * من الكاميرا أو معرض الصور.
 *
 * الرؤية ليست بسيطة هنا: لكل تصنيف وكل ملف قاعدة وصول خاصة، ولذلك نستدعي
 * ArchiveFile::paginate و isVisibleTo نفسيهما اللذين يستخدمهما الويب - فلا
 * يتسرّب ملف عبر الجوال لا يراه صاحبه على الموقع.
 */
class ArchiveApiController
{
    private const STORAGE_DIR = '/storage/uploads/archive';
    private const PER_PAGE = 20;

    /** GET /api/v1/archive?category_id=&q=&extension=&status=&page= */
    public function index(array $params = []): void
    {
        $companyId = $this->companyId();
        Api::requirePermission('archive.view');

        $page = max(1, (int) Api::input('page', 1));
        $filters = [
            'category_id' => (int) Api::input('category_id', 0) ?: null,
            'q' => trim((string) Api::input('q', '')) ?: null,
            'extension' => trim((string) Api::input('extension', '')) ?: null,
            'status' => trim((string) Api::input('status', '')) ?: null,
            'sort' => 'date_desc',
        ];

        $result = ArchiveFile::paginate(
            $companyId,
            (int) Auth::id(),
            Auth::isSystemAdmin(),
            $this->canManage(),
            Auth::isCompanyAdmin(),
            $filters,
            $page,
            self::PER_PAGE
        );

        Api::ok([
            'files' => array_map([$this, 'fileSummary'], $result['rows']),
            'total' => (int) $result['total'],
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'categories' => array_map(fn (array $c) => [
                'id' => (int) $c['id'],
                'name' => $c['name'],
                'depth' => (int) ($c['depth'] ?? 0),
                'parent_id' => $c['parent_id'] !== null ? (int) $c['parent_id'] : null,
            ], ArchiveCategory::tree($companyId)),
            'extensions' => ArchiveFile::allowedExtensions(),
            'can_upload' => Permission::check('archive.create'),
            'can_download' => Permission::check('archive.download'),
            'max_bytes' => ArchiveFile::MAX_BYTES,
        ]);
    }

    /** GET /api/v1/archive/{id} - تفاصيل ملف واحد. */
    public function show(array $params): void
    {
        $companyId = $this->companyId();
        Api::requirePermission('archive.view');

        [$file, $category] = $this->findVisible((int) $params['id'], $companyId);

        ArchiveFile::incrementView((int) $file['id']);

        // ArchiveFile::find لا يضمّ التصنيف ولا الرافع، فنكملهما هنا.
        $file['category_name'] = $category['name'] ?? null;
        $file['uploader_name'] = Database::first(
            'SELECT name FROM users WHERE id = :id',
            ['id' => (int) $file['created_by']]
        )['name'] ?? null;

        Api::ok([
            'file' => $this->fileSummary($file) + [
                'description' => $file['description'] ?? null,
                'keywords' => $file['keywords'] ?? null,
                'notes' => $file['notes'] ?? null,
                'expires_at' => $file['expires_at'] ?? null,
                'version' => (int) $file['version'],
                'view_count' => (int) $file['view_count'],
                'download_count' => (int) $file['download_count'],
                'linked_label' => $file['linked_label'] ?? null,
            ],
            'can_download' => Permission::check('archive.download'),
        ]);
    }

    /**
     * GET /api/v1/archive/{id}/file - بثّ الملف الخام (للمعاينة أو الحفظ).
     * يخضع لصلاحية التحميل نفسها لأن البثّ الخام يعادل التحميل.
     */
    public function download(array $params): void
    {
        $companyId = $this->companyId();
        Api::requirePermission('archive.download');

        [$file] = $this->findVisible((int) $params['id'], $companyId);

        $path = BASE_PATH . self::STORAGE_DIR . '/' . $file['company_id'] . '/' . $file['stored_name'];
        if (!is_file($path)) {
            Api::error('الملف غير موجود على الخادم.', 404, 'file_missing');
        }

        ArchiveFile::incrementDownload((int) $file['id']);
        ArchiveFileDownload::add((int) $file['id'], (int) Auth::id());
        ArchiveFileLog::add((int) $file['id'], (int) Auth::id(), 'downloaded', 'تم تحميل الملف من تطبيق الجوال');
        ActivityLog::log('archive.download', 'archive_file', (int) $file['id'], "تحميل ملف (جوال): {$file['original_name']}");

        header('Content-Type: ' . $this->mimeFor($file['extension']));
        header('Content-Disposition: inline; filename="' . rawurlencode($file['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /**
     * POST /api/v1/archive  (multipart/form-data)
     * الحقول: file, category_id, title?, description?, keywords?, expires_at?
     */
    public function store(): void
    {
        $companyId = $this->companyId();
        Api::requirePermission('archive.create');

        $categoryId = (int) Api::input('category_id', 0);
        $category = $categoryId ? ArchiveCategory::find($categoryId) : null;
        if (!$category || (int) $category['company_id'] !== $companyId) {
            Api::error('اختر تصنيفاً صحيحاً للملف.', 422, 'invalid_category');
        }

        $upload = Uploads::handleFile(
            'file',
            BASE_PATH . self::STORAGE_DIR . '/' . $companyId,
            ArchiveFile::allowedExtensions(),
            ArchiveFile::MAX_BYTES
        );
        if ($upload['error']) {
            Api::error($upload['error'], 422, 'upload_failed');
        }
        if (!$upload['filename']) {
            Api::error('لم يصل أي ملف.', 422, 'no_file');
        }

        $expiresAt = trim((string) Api::input('expires_at', ''));
        $expiresAt = ($expiresAt !== '' && strtotime($expiresAt)) ? date('Y-m-d', strtotime($expiresAt)) : null;

        $fileId = ArchiveFile::create([
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'original_name' => $upload['original'],
            'stored_name' => $upload['filename'],
            'extension' => $upload['extension'],
            'mime_type' => $this->mimeFor($upload['extension']),
            'size' => $upload['size'],
            'title' => mb_substr(trim((string) Api::input('title', '')), 0, 255) ?: null,
            'description' => mb_substr(trim((string) Api::input('description', '')), 0, 2000) ?: null,
            'keywords' => mb_substr(trim((string) Api::input('keywords', '')), 0, 500) ?: null,
            'status' => 'active',
            'visibility_type' => 'inherit',
            'expires_at' => $expiresAt,
            'created_by' => (int) Auth::id(),
        ]);

        ArchiveFileLog::add($fileId, (int) Auth::id(), 'uploaded', 'تم رفع الملف من تطبيق الجوال');
        ActivityLog::log('archive.upload', 'archive_file', $fileId, "رفع ملف (جوال): {$upload['original']}");

        Api::ok(['id' => $fileId, 'message' => 'تم رفع الملف بنجاح.']);
    }

    // ---------------------------------------------------------------- مساعدات

    private function companyId(): int
    {
        $companyId = (int) (Auth::companyId() ?? 0);
        if (!$companyId) {
            Api::error('لا توجد شركة مرتبطة بحسابك.', 400, 'no_company');
        }
        return $companyId;
    }

    private function canManage(): bool
    {
        return Permission::check('archive.manage');
    }

    /** يعيد [الملف، تصنيفه] بعد التأكد من رؤيته - أو 404/403. */
    private function findVisible(int $id, int $companyId): array
    {
        $file = ArchiveFile::find($id);
        if (!$file || (int) $file['company_id'] !== $companyId || !empty($file['deleted_at'])) {
            Api::error('الملف غير موجود.', 404, 'not_found');
        }
        $category = ArchiveCategory::find((int) $file['category_id']) ?? [];

        $visible = ArchiveFile::isVisibleTo(
            $file,
            $category,
            (int) Auth::id(),
            Auth::isSystemAdmin(),
            $this->canManage(),
            Auth::isCompanyAdmin()
        );
        if (!$visible) {
            Api::error('لا تملك صلاحية الوصول لهذا الملف.', 403, 'forbidden');
        }

        return [$file, $category];
    }

    private function fileSummary(array $file): array
    {
        $extension = strtolower((string) $file['extension']);

        return [
            'id' => (int) $file['id'],
            'original_name' => $file['original_name'],
            'title' => $file['title'] ?? null,
            'extension' => $extension,
            'icon' => ArchiveFile::icon($extension),
            'size' => (int) $file['size'],
            'status' => $file['status'],
            'category_id' => (int) $file['category_id'],
            'category_name' => $file['category_name'] ?? null,
            'uploader_name' => $file['uploader_name'] ?? null,
            'created_at' => $file['created_at'],
            // التطبيق يعرض الصور وPDF داخلياً، وغيرها يفتحه بتطبيق النظام.
            'is_image' => ArchiveFile::isImage($extension),
            'is_pdf' => ArchiveFile::isPdf($extension),
        ];
    }

    private function mimeFor(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/octet-stream',
        };
    }
}

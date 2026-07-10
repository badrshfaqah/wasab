<?php

namespace Modules\Archive\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use Modules\Archive\Models\ArchiveCategory;

class ArchiveCategoryController
{
    private const VISIBILITY = ['all', 'specific_users', 'system_admin_only', 'company_admin_only'];

    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('archive.view')) {
            $this->forbidden();
            return;
        }

        View::render('archive::categories.index', [
            'pageTitle' => 'تصنيفات الأرشيف',
            'categories' => ArchiveCategory::tree($companyId),
            'canCreate' => $this->can('archive.categories.create'),
            'canEdit' => $this->can('archive.categories.edit'),
            'canDelete' => $this->can('archive.categories.delete'),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('archive.categories.create')) {
            $this->forbidden();
            return;
        }

        View::render('archive::categories.form', [
            'pageTitle' => 'تصنيف جديد',
            'category' => null,
            'parentId' => (int) Request::query('parent_id', 0),
            'categories' => ArchiveCategory::tree($companyId),
            'companyUsers' => $this->companyUsers($companyId),
            'accessUserIds' => [],
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('archive.categories.create')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/archive/categories/create');

        $data = $this->validated($companyId);
        if ($data === null) {
            redirect('/archive/categories/create');
        }
        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();

        $categoryId = ArchiveCategory::create($data);
        if ($data['visibility_type'] === 'specific_users') {
            ArchiveCategory::setAccessUsers($categoryId, (array) Request::input('access_users', []));
        }

        ActivityLog::log('archive.category.create', 'archive_category', $categoryId, "إنشاء تصنيف: {$data['name']}");
        flash_set('success', 'تم إنشاء التصنيف.');
        redirect('/archive/categories');
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('archive.categories.edit')) {
            $this->forbidden();
            return;
        }
        $category = $this->findInCompany((int) $params['id'], $companyId);

        View::render('archive::categories.form', [
            'pageTitle' => 'تعديل تصنيف',
            'category' => $category,
            'parentId' => (int) $category['parent_id'],
            'categories' => array_filter(
                ArchiveCategory::tree($companyId),
                fn ($c) => (int) $c['id'] !== $category['id'] && !in_array((int) $c['id'], ArchiveCategory::childrenIds($category['id']), true)
            ),
            'companyUsers' => $this->companyUsers($companyId),
            'accessUserIds' => ArchiveCategory::accessUserIds($category['id']),
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('archive.categories.edit')) {
            $this->forbidden();
            return;
        }
        $category = $this->findInCompany((int) $params['id'], $companyId);
        $this->verifyCsrf('/archive/categories/' . $category['id'] . '/edit');

        $data = $this->validated($companyId, $category['id']);
        if ($data === null) {
            redirect('/archive/categories/' . $category['id'] . '/edit');
        }

        ArchiveCategory::update($category['id'], $data);
        ArchiveCategory::setAccessUsers(
            $category['id'],
            $data['visibility_type'] === 'specific_users' ? (array) Request::input('access_users', []) : []
        );

        ActivityLog::log('archive.category.update', 'archive_category', $category['id'], "تعديل تصنيف: {$data['name']}");
        flash_set('success', 'تم حفظ التعديلات.');
        redirect('/archive/categories');
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('archive.categories.delete')) {
            $this->forbidden();
            return;
        }
        $category = $this->findInCompany((int) $params['id'], $companyId);
        $this->verifyCsrf('/archive/categories');

        if (ArchiveCategory::hasChildren($category['id']) || ArchiveCategory::hasFiles($category['id'])) {
            flash_set('error', 'لا يمكن حذف تصنيف يحتوي على تصنيفات فرعية أو ملفات. انقل أو احذف محتوياته أولاً.');
            redirect('/archive/categories');
        }

        ArchiveCategory::delete($category['id']);
        ActivityLog::log('archive.category.delete', 'archive_category', $category['id'], "حذف تصنيف: {$category['name']}");
        flash_set('success', 'تم حذف التصنيف.');
        redirect('/archive/categories');
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

    private function findInCompany(int $id, int $companyId): array
    {
        $category = ArchiveCategory::find($id);
        if (!$category || (int) $category['company_id'] !== $companyId) {
            flash_set('error', 'التصنيف غير موجود.');
            redirect('/archive/categories');
        }
        return $category;
    }

    private function companyUsers(int $companyId): array
    {
        return Database::select('SELECT id, name FROM users WHERE company_id = :c AND status = "active" ORDER BY name', ['c' => $companyId]);
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

    private function validated(int $companyId, ?int $selfId = null): ?array
    {
        $name = trim((string) Request::input('name', ''));
        $parentId = (int) Request::input('parent_id', 0) ?: null;
        $visibility = Request::input('visibility_type', 'all');

        if ($name === '') {
            flash_set('error', 'اسم التصنيف مطلوب.');
            return null;
        }
        if (!in_array($visibility, self::VISIBILITY, true)) {
            $visibility = 'all';
        }

        if ($parentId) {
            $parent = ArchiveCategory::find($parentId);
            if (!$parent || (int) $parent['company_id'] !== $companyId) {
                flash_set('error', 'التصنيف الأب المختار غير صالح.');
                return null;
            }
            if ($selfId && ($parentId === $selfId || in_array($parentId, ArchiveCategory::childrenIds($selfId), true))) {
                flash_set('error', 'لا يمكن جعل التصنيف تابعاً لنفسه أو لأحد تصنيفاته الفرعية.');
                return null;
            }
        }

        return [
            'name' => $name,
            'parent_id' => $parentId,
            'visibility_type' => $visibility,
        ];
    }
}

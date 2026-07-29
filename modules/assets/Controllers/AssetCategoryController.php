<?php

namespace Modules\Assets\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;
use Modules\Assets\Models\AssetCategory;

class AssetCategoryController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        View::render('assets::categories', [
            'pageTitle' => 'تصنيفات الأصول',
            'categories' => AssetCategory::forCompany($companyId),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/custody/categories');

        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم التصنيف مطلوب.');
            redirect('/custody/categories');
        }
        $id = AssetCategory::create($companyId, $name);
        ActivityLog::log('assets.category_create', 'asset_category', $id, "إضافة تصنيف أصول: {$name}");
        flash_set('success', 'تمت إضافة التصنيف.');
        redirect('/custody/categories');
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $cat = AssetCategory::find((int) $params['id']);
        if (!$cat || (int) $cat['company_id'] !== $companyId) {
            flash_set('error', 'التصنيف غير موجود.');
            redirect('/custody/categories');
        }
        $this->verifyCsrf('/custody/categories');

        AssetCategory::delete((int) $cat['id']);
        ActivityLog::log('assets.category_delete', 'asset_category', (int) $cat['id'], "حذف تصنيف أصول: {$cat['name']}");
        flash_set('success', 'تم حذف التصنيف (الأصول بقيت بلا تصنيف).');
        redirect('/custody/categories');
    }

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('assets::no-company', ['pageTitle' => 'العهد والأصول']);
            exit;
        }
        return $companyId;
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('assets.manage');
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

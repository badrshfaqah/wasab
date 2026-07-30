<?php

namespace Modules\Assets\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use Modules\Assets\Models\Asset;
use Modules\Assets\Models\AssetCategory;
use Modules\Assets\Models\AssetHandover;
use Modules\Assets\Models\AssetLog;

class AssetController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();

        // الموظف الذي يملك view_own فقط (لا view) يُحوَّل لصفحة عهده الخاصة
        if (!$this->can('assets.view') && $this->can('assets.view_own')) {
            redirect('/custody/my');
        }
        if (!$this->can('assets.view')) {
            $this->forbidden();
            return;
        }

        $filters = [];
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        if (in_array(Request::query('status', ''), Asset::STATUSES, true)) {
            $filters['status'] = Request::query('status');
        }
        if ($catId = (int) Request::query('category_id', 0)) {
            $filters['category_id'] = $catId;
        }

        $page = max(1, (int) Request::query('page', 1));
        $result = Asset::paginate($companyId, $page, 20, $filters);

        View::render('assets::assets.index', [
            'pageTitle' => 'العهد والأصول',
            'assets' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'filters' => $filters,
            'categories' => AssetCategory::forCompany($companyId),
            'statusLabels' => Asset::statusLabels(),
            'canManage' => $this->canManage(),
            'canCreate' => $this->can('assets.create'),
            'canAssign' => $this->can('assets.assign'),
        ]);
    }

    /** عهدي: العهد المسندة للمستخدم الحالي (المربوط بحساب). */
    public function mine(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.view') && !$this->can('assets.view_own')) {
            $this->forbidden();
            return;
        }

        View::render('assets::assets.mine', [
            'pageTitle' => 'عهدي',
            'assets' => Asset::currentlyHeldByUser($companyId, Auth::id()),
            'statusLabels' => Asset::statusLabels(),
        ]);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.view')) {
            $this->forbidden();
            return;
        }
        $asset = $this->findVisible((int) $params['id'], $companyId);

        View::render('assets::assets.show', [
            'pageTitle' => $asset['name'],
            'asset' => $asset,
            'logs' => AssetLog::forAsset($asset['id']),
            'openItem' => $asset['status'] === 'assigned' ? AssetHandover::openItemForAsset($asset['id']) : null,
            'statusLabels' => Asset::statusLabels(),
            'canEdit' => $this->can('assets.edit'),
            'canDelete' => $this->can('assets.delete'),
            'canAssign' => $this->can('assets.assign'),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.create')) {
            $this->forbidden();
            return;
        }
        View::render('assets::assets.form', [
            'pageTitle' => 'إضافة أصل',
            'asset' => null,
            'categories' => AssetCategory::forCompany($companyId),
            'statusLabels' => Asset::statusLabels(),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.create')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/custody/create');

        $data = $this->validated($companyId);
        if ($data === null) {
            redirect('/custody/create');
        }
        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();
        $data['created_at'] = date('Y-m-d H:i:s');

        $photo = Uploads::handleImage('photo', BASE_PATH . '/storage/uploads/assets/' . $companyId);
        if ($photo['filename']) {
            $data['photo'] = $photo['filename'];
        }

        $assetId = Asset::create($data);
        AssetLog::add($assetId, Auth::id(), 'created', 'تم تسجيل الأصل');
        ActivityLog::log('assets.create', 'asset', $assetId, "إضافة أصل: {$data['name']}");

        flash_set('success', 'تمت إضافة الأصل.');
        redirect('/custody/' . $assetId);
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.edit')) {
            $this->forbidden();
            return;
        }
        $asset = $this->findVisible((int) $params['id'], $companyId);

        View::render('assets::assets.form', [
            'pageTitle' => 'تعديل: ' . $asset['name'],
            'asset' => $asset,
            'categories' => AssetCategory::forCompany($companyId),
            'statusLabels' => Asset::statusLabels(),
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.edit')) {
            $this->forbidden();
            return;
        }
        $asset = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/custody/' . $asset['id'] . '/edit');

        $data = $this->validated($companyId);
        if ($data === null) {
            redirect('/custody/' . $asset['id'] . '/edit');
        }
        // الحالة والحامل يُداران عبر الإسناد/الإرجاع لا التعديل المباشر - لا نمسّهما هنا
        unset($data['status']);
        $data['updated_at'] = date('Y-m-d H:i:s');

        $photo = Uploads::handleImage('photo', BASE_PATH . '/storage/uploads/assets/' . $companyId);
        if ($photo['filename']) {
            $data['photo'] = $photo['filename'];
        }

        Asset::update((int) $asset['id'], $data);
        AssetLog::add((int) $asset['id'], Auth::id(), 'updated', 'تعديل بيانات الأصل');
        ActivityLog::log('assets.update', 'asset', (int) $asset['id'], "تعديل أصل: {$data['name']}");

        flash_set('success', 'تم حفظ التعديلات.');
        redirect('/custody/' . $asset['id']);
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.delete')) {
            $this->forbidden();
            return;
        }
        $asset = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/custody/' . $asset['id']);

        if ($asset['status'] === 'assigned') {
            flash_set('error', 'لا يمكن حذف أصل تحت عهدة حالية - أرجعه أولاً.');
            redirect('/custody/' . $asset['id']);
        }

        Asset::delete((int) $asset['id']);
        ActivityLog::log('assets.delete', 'asset', (int) $asset['id'], "حذف أصل: {$asset['name']}");
        flash_set('success', 'تم حذف الأصل.');
        redirect('/custody');
    }

    /** تغيير الحالة يدوياً (صيانة/خارج الخدمة/مفقود/إعادة للإتاحة) - غير الإسناد. */
    public function changeStatus(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.edit')) {
            $this->forbidden();
            return;
        }
        $asset = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/custody/' . $asset['id']);

        $status = Request::input('status');
        // الحالات القابلة للضبط اليدوي (الإسناد/الإرجاع لهما مساراهما الخاص)
        $manual = ['available', 'maintenance', 'retired', 'lost'];
        if (!in_array($status, $manual, true)) {
            flash_set('error', 'حالة غير صحيحة.');
            redirect('/custody/' . $asset['id']);
        }
        if ($asset['status'] === 'assigned') {
            flash_set('error', 'الأصل تحت عهدة - أرجعه أولاً قبل تغيير حالته.');
            redirect('/custody/' . $asset['id']);
        }

        Asset::update((int) $asset['id'], ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        AssetLog::add((int) $asset['id'], Auth::id(), 'status_changed', 'تغيير الحالة إلى: ' . (Asset::statusLabels()[$status] ?? $status));
        flash_set('success', 'تم تحديث حالة الأصل.');
        redirect('/custody/' . $asset['id']);
    }

    // ---------------------------------------------------------------

    private function validated(int $companyId): ?array
    {
        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم الأصل مطلوب.');
            return null;
        }

        $categoryId = (int) Request::input('category_id', 0) ?: null;
        if ($categoryId) {
            $cat = AssetCategory::find($categoryId);
            if (!$cat || (int) $cat['company_id'] !== $companyId) {
                $categoryId = null;
            }
        }

        $status = Request::input('status', 'available');
        if (!in_array($status, Asset::STATUSES, true)) {
            $status = 'available';
        }

        $cost = Request::input('purchase_cost');
        $cost = ($cost !== null && $cost !== '') ? (float) $cost : null;

        return [
            'name' => mb_substr($name, 0, 180),
            'category_id' => $categoryId,
            'asset_code' => mb_substr(trim((string) Request::input('asset_code', '')), 0, 80) ?: null,
            'serial_number' => mb_substr(trim((string) Request::input('serial_number', '')), 0, 120) ?: null,
            'status' => $status,
            'condition_note' => mb_substr(trim((string) Request::input('condition_note', '')), 0, 60) ?: null,
            'purchase_date' => Request::input('purchase_date') ?: null,
            'purchase_cost' => $cost,
            'warranty_expiry' => Request::input('warranty_expiry') ?: null,
            'notes' => trim((string) Request::input('notes', '')) ?: null,
        ];
    }

    /** تصدير قائمة الأصول (بنفس فلاتر الصفحة) إلى CSV أو طباعة/PDF. */
    public function export(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('assets.view')) {
            $this->forbidden();
            return;
        }
        $filters = [];
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        if (in_array(Request::query('status', ''), Asset::STATUSES, true)) {
            $filters['status'] = Request::query('status');
        }
        if ($catId = (int) Request::query('category_id', 0)) {
            $filters['category_id'] = $catId;
        }
        $assets = Asset::paginate($companyId, 1, 100000, $filters)['rows'];
        $labels = Asset::statusLabels();

        $headers = ['الأصل', 'الرمز', 'الرقم التسلسلي', 'التصنيف', 'الحالة', 'الحامل الحالي', 'قيمة الشراء', 'انتهاء الضمان'];
        $rows = array_map(fn ($a) => [
            $a['name'],
            $a['asset_code'] ?? '-',
            $a['serial_number'] ?? '-',
            $a['category_name'] ?? '-',
            $labels[$a['status']] ?? $a['status'],
            $a['current_holder_name'] ?? '-',
            $a['purchase_cost'] !== null ? number_format((float) $a['purchase_cost'], 2) : '-',
            $a['warranty_expiry'] ? format_date($a['warranty_expiry']) : '-',
        ], $assets);

        if (($params['format'] ?? 'csv') === 'print') {
            \App\Core\Export::printable('العهد والأصول', $headers, $rows);
        }
        \App\Core\Export::csv('assets', $headers, $rows);
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

    private function can(string $key): bool
    {
        return Permission::check($key);
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('assets.manage');
    }

    private function findVisible(int $id, int $companyId): array
    {
        $asset = Asset::find($id);
        if (!$asset || (int) $asset['company_id'] !== $companyId) {
            flash_set('error', 'الأصل غير موجود.');
            redirect('/custody');
        }
        return $asset;
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

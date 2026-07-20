<?php

namespace Modules\Inbox\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\View;
use Modules\Inbox\Models\InboxSite;

/**
 * إدارة المواقع المصدر ومفاتيح ربطها: كل شركة تدير مواقعها فقط (مدير الشركة أو
 * من يملك صلاحية inbox.manage_sites)، والمفتاح يولَّد عشوائياً من الخادم ولا يُدخل يدوياً.
 */
class InboxSiteController
{
    private const PLATFORMS = ['wordpress', 'custom'];

    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }

        View::render('inbox::sites.index', [
            'pageTitle' => 'مواقع مركز المراسلات',
            'sites' => InboxSite::forCompany($companyId),
            'endpointUrl' => route('/api/inbox'),
        ]);
    }

    public function create(): void
    {
        $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }

        View::render('inbox::sites.form', [
            'pageTitle' => 'إضافة موقع',
            'site' => null,
            'platforms' => self::PLATFORMS,
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/inbox/sites/create');

        $data = $this->validated();
        if ($data === null) {
            redirect('/inbox/sites/create');
        }

        $data['company_id'] = $companyId;
        $data['api_key'] = InboxSite::generateApiKey();
        $data['created_by'] = Auth::id();
        $data['created_at'] = date('Y-m-d H:i:s');

        $siteId = InboxSite::create($data);
        ActivityLog::log('inbox.site_create', 'inbox_site', $siteId, "إضافة موقع لمركز المراسلات: {$data['name']}");

        flash_set('success', 'تمت إضافة الموقع، انسخ مفتاح الربط وكود التركيب من القائمة.');
        redirect('/inbox/sites');
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $site = $this->findVisible((int) $params['id'], $companyId);

        View::render('inbox::sites.form', [
            'pageTitle' => 'تعديل موقع',
            'site' => $site,
            'platforms' => self::PLATFORMS,
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $site = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/inbox/sites/' . $site['id'] . '/edit');

        $data = $this->validated();
        if ($data === null) {
            redirect('/inbox/sites/' . $site['id'] . '/edit');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        InboxSite::update((int) $site['id'], $data);
        ActivityLog::log('inbox.site_update', 'inbox_site', (int) $site['id'], "تعديل موقع بمركز المراسلات: {$data['name']}");

        flash_set('success', 'تم حفظ التعديلات.');
        redirect('/inbox/sites');
    }

    /**
     * إعادة توليد المفتاح تُبطل المفتاح القديم فوراً - أي موقع ما زال يستخدمه
     * سيُرفض حتى يُحدَّث كوده بالمفتاح الجديد.
     */
    public function regenerate(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $site = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/inbox/sites');

        InboxSite::update((int) $site['id'], [
            'api_key' => InboxSite::generateApiKey(),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::log('inbox.site_regenerate', 'inbox_site', (int) $site['id'], "إعادة توليد مفتاح ربط موقع: {$site['name']}");

        flash_set('success', 'تم توليد مفتاح جديد لموقع ' . $site['name'] . '. حدّث الكود بالموقع بالمفتاح الجديد.');
        redirect('/inbox/sites');
    }

    public function toggle(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $site = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/inbox/sites');

        $newStatus = $site['status'] === 'active' ? 'disabled' : 'active';
        InboxSite::update((int) $site['id'], ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')]);
        ActivityLog::log('inbox.site_toggle', 'inbox_site', (int) $site['id'], ($newStatus === 'active' ? 'تفعيل' : 'تعطيل') . " موقع بمركز المراسلات: {$site['name']}");

        flash_set('success', $newStatus === 'active' ? 'تم تفعيل الموقع.' : 'تم تعطيل الموقع - سيُرفض أي إرسال منه حتى يُعاد تفعيله.');
        redirect('/inbox/sites');
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $site = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/inbox/sites');

        InboxSite::delete((int) $site['id']);
        ActivityLog::log('inbox.site_delete', 'inbox_site', (int) $site['id'], "حذف موقع من مركز المراسلات مع كل رسائله: {$site['name']}");

        flash_set('success', 'تم حذف الموقع وكل رسائله.');
        redirect('/inbox/sites');
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('inbox::no-company', ['pageTitle' => 'مركز المراسلات']);
            exit;
        }
        return $companyId;
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || \App\Core\Permission::check('inbox.manage_sites');
    }

    private function findVisible(int $id, int $companyId): array
    {
        $site = InboxSite::find($id);
        if (!$site || (int) $site['company_id'] !== $companyId) {
            flash_set('error', 'الموقع غير موجود.');
            redirect('/inbox/sites');
        }
        return $site;
    }

    private function validated(): ?array
    {
        $name = trim((string) Request::input('name', ''));
        $url = trim((string) Request::input('url', ''));
        $platform = Request::input('platform', 'custom');

        if ($name === '') {
            flash_set('error', 'اسم الموقع مطلوب.');
            return null;
        }
        if (!in_array($platform, self::PLATFORMS, true)) {
            $platform = 'custom';
        }
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            flash_set('error', 'رابط الموقع غير صحيح.');
            return null;
        }

        return [
            'name' => mb_substr($name, 0, 120),
            'url' => $url !== '' ? mb_substr($url, 0, 255) : null,
            'platform' => $platform,
        ];
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

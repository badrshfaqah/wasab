<?php

namespace Modules\Crm\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use Modules\Crm\Models\CrmLog;
use Modules\Crm\Models\Organization;
use Modules\Crm\Models\Workspace;

class OrganizationController extends BaseCrmController
{
    /** لوحة المساحة: جهاتها مع الفلاتر - نقطة الدخول اليومية. */
    public function index(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);

        $filters = array_filter([
            'q' => trim((string) Request::query('q', '')),
            'category' => (int) Request::query('category', 0),
            'owner' => (int) Request::query('owner', 0),
            'city' => trim((string) Request::query('city', '')),
            'due' => Request::query('due') ? 1 : 0,
        ]);
        $page = max(1, (int) Request::query('page', 1));
        $perPage = 20;

        View::render('crm::orgs.index', [
            'pageTitle' => $workspace['name'],
            'workspace' => $workspace,
            'membership' => $membership,
            'rows' => Organization::inWorkspace((int) $workspace['id'], $filters, $perPage, ($page - 1) * $perPage),
            'total' => Organization::countInWorkspace((int) $workspace['id'], $filters),
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters,
            'categories' => Database::select('SELECT * FROM crm_categories WHERE workspace_id = :w ORDER BY sort_order, name', ['w' => $workspace['id']]),
            'members' => Workspace::members((int) $workspace['id']),
            'canCreate' => Workspace::can($membership, 'orgs.create'),
            'canSettings' => Workspace::can($membership, 'settings.manage'),
        ]);
    }

    /** إضافة جهة للمساحة: بحث في الدليل المركزي أولاً منعاً للتكرار. */
    public function create(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.create');

        $q = trim((string) Request::query('q', ''));
        $matches = $q !== '' ? Organization::search($companyId, $q) : [];
        // نستبعد ما هو مرتبط بالمساحة أصلاً
        foreach ($matches as $i => $m) {
            if (Organization::relation((int) $workspace['id'], (int) $m['id'])) {
                $matches[$i]['already_linked'] = true;
            }
        }

        View::render('crm::orgs.create', [
            'pageTitle' => 'إضافة جهة',
            'workspace' => $workspace,
            'membership' => $membership,
            'q' => $q,
            'matches' => $matches,
            'categories' => Database::select('SELECT * FROM crm_categories WHERE workspace_id = :w ORDER BY sort_order, name', ['w' => $workspace['id']]),
            'members' => Workspace::members((int) $workspace['id']),
        ]);
    }

    /** ربط جهة قائمة من الدليل بالمساحة الحالية. */
    public function link(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.create');
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/orgs/create');

        $organization = Organization::find((int) Request::input('organization_id', 0));
        if (!$organization || (int) $organization['company_id'] !== $companyId) {
            flash_set('error', 'الجهة غير موجودة.');
            redirect('/crm/w/' . $workspace['id'] . '/orgs/create');
        }

        $relationId = Organization::link((int) $workspace['id'], (int) $organization['id'], [
            'owner_id' => Auth::id(),
            'added_by' => Auth::id(),
        ]);
        $categories = array_map('intval', (array) Request::input('categories', []));
        if ($categories) {
            Organization::setCategories($relationId, $this->validCategoryIds((int) $workspace['id'], $categories));
        }

        CrmLog::add((int) $workspace['id'], 'org.link', 'organization', (int) $organization['id'],
            'ربط جهة قائمة بالمساحة: ' . $organization['name']);
        flash_set('success', 'رُبطت «' . $organization['name'] . '» بالمساحة — بياناتها الأساسية مشتركة مع بقية المساحات.');
        redirect('/crm/w/' . $workspace['id'] . '/orgs/' . $organization['id']);
    }

    /** إنشاء جهة جديدة في الدليل وربطها بالمساحة. */
    public function store(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.create');
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/orgs/create');

        $data = $this->validatedOrg($companyId);
        if ($data === null) {
            redirect('/crm/w/' . $workspace['id'] . '/orgs/create');
        }

        $organizationId = Organization::create($data + [
            'company_id' => $companyId,
            'created_by' => Auth::id(),
        ]);
        $relationId = Organization::link((int) $workspace['id'], $organizationId, [
            'owner_id' => Auth::id(),
            'added_by' => Auth::id(),
        ]);
        $categories = array_map('intval', (array) Request::input('categories', []));
        if ($categories) {
            Organization::setCategories($relationId, $this->validCategoryIds((int) $workspace['id'], $categories));
        }

        CrmLog::add((int) $workspace['id'], 'org.create', 'organization', $organizationId, 'إضافة جهة: ' . $data['name']);
        ActivityLog::log('crm.org.create', 'crm_organization', $organizationId, "إضافة جهة CRM: {$data['name']}");
        flash_set('success', 'أُضيفت الجهة إلى الدليل والمساحة.');
        redirect('/crm/w/' . $workspace['id'] . '/orgs/' . $organizationId);
    }

    /** ملف الجهة 360° داخل سياق المساحة. */
    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);

        $organization = Organization::find((int) $params['orgId']);
        $relation = $organization ? Organization::relation((int) $workspace['id'], (int) $organization['id']) : null;
        if (!$organization || (int) $organization['company_id'] !== $companyId || !$relation) {
            flash_set('error', 'الجهة غير مرتبطة بهذه المساحة.');
            redirect('/crm/w/' . $workspace['id']);
        }

        // المساحات الأخرى المرتبطة بالجهة: تُعرض فقط ما يصل إليه المستخدم
        $otherSpaces = [];
        foreach (Organization::workspacesOf((int) $organization['id']) as $link) {
            if ((int) $link['workspace_id'] === (int) $workspace['id']) {
                continue;
            }
            if (Workspace::membership((int) $link['workspace_id'], Auth::id())) {
                $otherSpaces[] = $link;
            }
        }

        View::render('crm::orgs.show', [
            'pageTitle' => $organization['name'],
            'workspace' => $workspace,
            'membership' => $membership,
            'organization' => $organization,
            'relation' => $relation,
            'categories' => Organization::categoriesOf((int) $relation['id']),
            'allCategories' => Database::select('SELECT * FROM crm_categories WHERE workspace_id = :w ORDER BY sort_order, name', ['w' => $workspace['id']]),
            'contacts' => Database::select('SELECT * FROM crm_contacts WHERE organization_id = :o ORDER BY name', ['o' => $organization['id']]),
            'otherSpaces' => $otherSpaces,
            'members' => Workspace::members((int) $workspace['id']),
            'logs' => CrmLog::forEntity('organization', (int) $organization['id'], 15),
            'canEdit' => Workspace::can($membership, 'orgs.edit'),
            'canDelete' => Workspace::can($membership, 'orgs.delete'),
        ]);
    }

    /** تعديل بيانات الجهة المركزية + علاقتها بالمساحة. */
    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.edit');
        $organization = Organization::find((int) $params['orgId']);
        $relation = $organization ? Organization::relation((int) $workspace['id'], (int) $organization['id']) : null;
        if (!$organization || (int) $organization['company_id'] !== $companyId || !$relation) {
            flash_set('error', 'الجهة غير مرتبطة بهذه المساحة.');
            redirect('/crm/w/' . $workspace['id']);
        }
        $back = '/crm/w/' . $workspace['id'] . '/orgs/' . $organization['id'];
        $this->verifyCsrf($back);

        $data = $this->validatedOrg($companyId, (int) $organization['id']);
        if ($data === null) {
            redirect($back);
        }
        Organization::update((int) $organization['id'], $data);

        // بيانات العلاقة داخل هذه المساحة فقط
        Database::update('crm_workspace_orgs', [
            'owner_id' => (int) Request::input('owner_id', 0) ?: null,
            'relation_status' => mb_substr(trim((string) Request::input('relation_status', '')), 0, 60) ?: null,
            'notes' => trim((string) Request::input('relation_notes', '')) ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $relation['id']]);

        Organization::setCategories(
            (int) $relation['id'],
            $this->validCategoryIds((int) $workspace['id'], array_map('intval', (array) Request::input('categories', [])))
        );

        CrmLog::add((int) $workspace['id'], 'org.update', 'organization', (int) $organization['id'], 'تعديل بيانات الجهة');
        flash_set('success', 'حُفظت البيانات.');
        redirect($back);
    }

    /** إزالة الجهة من المساحة - تبقى في الدليل المركزي وفي بقية المساحات. */
    public function unlink(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.delete');
        $organization = Organization::find((int) $params['orgId']);
        if (!$organization) {
            redirect('/crm/w/' . $workspace['id']);
        }
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/orgs/' . $organization['id']);

        Organization::unlink((int) $workspace['id'], (int) $organization['id']);
        CrmLog::add((int) $workspace['id'], 'org.unlink', 'organization', (int) $organization['id'],
            'إزالة الجهة من المساحة: ' . $organization['name']);
        flash_set('success', 'أُزيلت الجهة من هذه المساحة (وبقيت في الدليل المركزي).');
        redirect('/crm/w/' . $workspace['id']);
    }

    /** الدليل المركزي: كل جهات الشركة ومساحاتها - لمن يدير CRM. */
    public function directory(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!Workspace::isAdmin()) {
            $this->forbidden();
        }
        $q = trim((string) Request::query('q', ''));
        $rows = $q !== ''
            ? Organization::search($companyId, $q, 100)
            : Database::select('SELECT id, name, trade_name, city, sector FROM crm_organizations WHERE company_id = :c ORDER BY name LIMIT 100', ['c' => $companyId]);

        foreach ($rows as $i => $row) {
            $rows[$i]['spaces'] = Organization::workspacesOf((int) $row['id']);
        }

        View::render('crm::directory', [
            'pageTitle' => 'دليل الجهات المركزي',
            'rows' => $rows,
            'q' => $q,
            'total' => Database::count('crm_organizations', 'company_id = :c', ['c' => $companyId]),
        ]);
    }

    // ---------------------------------------------------------------

    private function validCategoryIds(int $workspaceId, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::select(
            "SELECT id FROM crm_categories WHERE workspace_id = ? AND id IN ({$placeholders})",
            array_merge([$workspaceId], $ids)
        );
        return array_map(fn ($r) => (int) $r['id'], $rows);
    }

    private function validatedOrg(int $companyId, ?int $existingId = null): ?array
    {
        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم الجهة مطلوب.');
            return null;
        }

        $social = [];
        foreach (['linkedin', 'twitter', 'instagram'] as $network) {
            $value = trim((string) Request::input('social_' . $network, ''));
            if ($value !== '') {
                $social[$network] = mb_substr($value, 0, 255);
            }
        }

        $data = [
            'name' => mb_substr($name, 0, 200),
            'trade_name' => mb_substr(trim((string) Request::input('trade_name', '')), 0, 200) ?: null,
            'description' => trim((string) Request::input('description', '')) ?: null,
            'sector' => mb_substr(trim((string) Request::input('sector', '')), 0, 120) ?: null,
            'country' => mb_substr(trim((string) Request::input('country', '')), 0, 80) ?: null,
            'city' => mb_substr(trim((string) Request::input('city', '')), 0, 80) ?: null,
            'address' => mb_substr(trim((string) Request::input('address', '')), 0, 255) ?: null,
            'website' => mb_substr(trim((string) Request::input('website', '')), 0, 200) ?: null,
            'email' => mb_substr(trim((string) Request::input('email', '')), 0, 150) ?: null,
            'phone' => mb_substr(trim((string) Request::input('phone', '')), 0, 50) ?: null,
            'social_json' => $social ? json_encode($social, JSON_UNESCAPED_UNICODE) : null,
            'notes' => trim((string) Request::input('notes', '')) ?: null,
        ];

        $upload = Uploads::handleImage('logo', BASE_PATH . '/storage/uploads/crm/' . $companyId);
        if ($upload['error']) {
            flash_set('error', $upload['error']);
            return null;
        }
        if ($upload['filename']) {
            $data['logo'] = $upload['filename'];
        }

        return $data;
    }
}

<?php

namespace Modules\Crm\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Notification;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;
use Modules\Crm\Models\CrmLog;
use Modules\Crm\Models\Workspace;

class WorkspaceController extends BaseCrmController
{
    /** الصفحة الرئيسية: المساحات التي يصل إليها المستخدم فقط. */
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        $showArchived = (string) Request::query('archived', '') === '1';

        View::render('crm::workspaces.index', [
            'pageTitle' => 'مساحات CRM',
            'workspaces' => Workspace::forUser($companyId, Auth::id(), $showArchived),
            'showArchived' => $showArchived,
            'canCreate' => Permission::check('crm.workspace.create') || Workspace::isAdmin(),
            'isAdmin' => Workspace::isAdmin(),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardCreate();

        View::render('crm::workspaces.form', [
            'pageTitle' => 'مساحة CRM جديدة',
            'workspace' => null,
            'members' => [],
            'companyUsers' => $this->companyUsers($companyId),
            'roleLabels' => Workspace::roleLabels(),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardCreate();
        $this->verifyCsrf('/crm/workspaces/create');

        $data = $this->validated();
        if ($data === null) {
            redirect('/crm/workspaces/create');
        }

        $workspaceId = Workspace::create($data + [
            'company_id' => $companyId,
            'created_by' => Auth::id(),
        ]);

        // منشئ المساحة ومسؤولها عضوان بدور مدير دائماً
        Workspace::setMember($workspaceId, Auth::id(), 'manager');
        if (!empty($data['manager_id']) && (int) $data['manager_id'] !== Auth::id()) {
            Workspace::setMember($workspaceId, (int) $data['manager_id'], 'manager');
            Notification::send(
                (int) $data['manager_id'],
                '🤝 عُيّنت مديراً لمساحة CRM',
                $data['name'],
                route('/crm/w/' . $workspaceId)
            );
        }
        $this->syncMembersFromRequest($workspaceId, $companyId, (string) $data['name']);
        $this->seedDefaults($workspaceId);

        CrmLog::add($workspaceId, 'workspace.create', 'workspace', $workspaceId, 'إنشاء مساحة: ' . $data['name']);
        ActivityLog::log('crm.workspace.create', 'crm_workspace', $workspaceId, "إنشاء مساحة CRM: {$data['name']}");
        flash_set('success', 'أُنشئت المساحة — أضف جهاتك وابدأ العمل.');
        redirect('/crm/w/' . $workspaceId);
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'settings.manage');

        View::render('crm::workspaces.form', [
            'pageTitle' => 'إعدادات المساحة',
            'workspace' => $workspace,
            'members' => Workspace::members((int) $workspace['id']),
            'companyUsers' => $this->companyUsers($companyId),
            'roleLabels' => Workspace::roleLabels(),
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'settings.manage');
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/edit');

        $data = $this->validated();
        if ($data === null) {
            redirect('/crm/w/' . $workspace['id'] . '/edit');
        }
        Workspace::update((int) $workspace['id'], $data);
        if (!empty($data['manager_id'])) {
            Workspace::setMember((int) $workspace['id'], (int) $data['manager_id'], 'manager');
        }

        CrmLog::add((int) $workspace['id'], 'workspace.update', 'workspace', (int) $workspace['id'], 'تعديل إعدادات المساحة');
        flash_set('success', 'حُفظت إعدادات المساحة.');
        redirect('/crm/w/' . $workspace['id'] . '/edit');
    }

    /** شاشة الأعضاء والصلاحيات التفصيلية. */
    public function members(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'settings.manage');

        $members = Workspace::members((int) $workspace['id']);
        foreach ($members as &$m) {
            $m['abilities'] = Workspace::abilitiesOf($m);
        }
        unset($m);

        View::render('crm::workspaces.members', [
            'pageTitle' => 'أعضاء المساحة',
            'workspace' => $workspace,
            'members' => $members,
            'companyUsers' => $this->companyUsers($companyId),
            'roleLabels' => Workspace::roleLabels(),
            'abilityLabels' => Workspace::ABILITIES,
            'roleDefaults' => Workspace::ROLE_DEFAULTS,
        ]);
    }

    public function saveMember(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'settings.manage');
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/members');

        $userId = (int) Request::input('user_id', 0);
        $target = Database::first(
            "SELECT id, name FROM users WHERE id = :id AND company_id = :c AND status = 'active'",
            ['id' => $userId, 'c' => $companyId]
        );
        if (!$target) {
            flash_set('error', 'اختر موظفاً صحيحاً من الشركة.');
            redirect('/crm/w/' . $workspace['id'] . '/members');
        }

        $role = Request::input('role', 'member');
        if (!array_key_exists($role, Workspace::roleLabels())) {
            $role = 'member';
        }
        // قدرات مخصصة اختيارية - وإلا يأخذ العضو قدرات دوره الافتراضية
        $abilities = array_values(array_intersect(
            (array) Request::input('abilities', []),
            array_keys(Workspace::ABILITIES)
        ));
        $isNew = !Database::first(
            'SELECT id FROM crm_workspace_members WHERE workspace_id = :w AND user_id = :u',
            ['w' => $workspace['id'], 'u' => $userId]
        );

        Workspace::setMember((int) $workspace['id'], $userId, $role, $abilities ?: null);
        CrmLog::add((int) $workspace['id'], $isNew ? 'member.add' : 'member.update', 'workspace', (int) $workspace['id'],
            ($isNew ? 'إضافة عضو: ' : 'تعديل صلاحيات: ') . $target['name'] . ' (' . Workspace::roleLabels()[$role] . ')');

        if ($isNew) {
            Notification::send($userId, '🤝 أُضفت إلى مساحة CRM', $workspace['name'], route('/crm/w/' . $workspace['id']));
        }
        flash_set('success', $isNew ? 'أُضيف العضو.' : 'حُدّثت صلاحيات العضو.');
        redirect('/crm/w/' . $workspace['id'] . '/members');
    }

    public function removeMember(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'settings.manage');
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/members');

        $userId = (int) $params['userId'];
        if ($userId === (int) $workspace['created_by']) {
            flash_set('error', 'لا يمكن إزالة منشئ المساحة.');
            redirect('/crm/w/' . $workspace['id'] . '/members');
        }
        Workspace::removeMember((int) $workspace['id'], $userId);
        CrmLog::add((int) $workspace['id'], 'member.remove', 'workspace', (int) $workspace['id'], 'إزالة عضو من المساحة');
        flash_set('success', 'أُزيل العضو من المساحة.');
        redirect('/crm/w/' . $workspace['id'] . '/members');
    }

    /** أرشفة المساحة أو إعادتها - لا تُحذف بياناتها. */
    public function toggleArchive(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'settings.manage');
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/edit');

        $newStatus = $workspace['status'] === 'active' ? 'archived' : 'active';
        Workspace::update((int) $workspace['id'], ['status' => $newStatus]);
        CrmLog::add((int) $workspace['id'], 'workspace.' . $newStatus, 'workspace', (int) $workspace['id'],
            $newStatus === 'archived' ? 'أرشفة المساحة' : 'إعادة تنشيط المساحة');
        flash_set('success', $newStatus === 'archived' ? 'أُرشفت المساحة.' : 'أُعيد تنشيط المساحة.');
        redirect('/crm');
    }

    // ---------------------------------------------------------------

    private function guardCreate(): void
    {
        if (!Permission::check('crm.workspace.create') && !Workspace::isAdmin()) {
            $this->forbidden();
        }
    }

    private function validated(): ?array
    {
        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم المساحة مطلوب.');
            return null;
        }
        $color = (string) Request::input('color', '#2563eb');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#2563eb';
        }
        $icon = trim((string) Request::input('icon', '🤝'));

        return [
            'name' => mb_substr($name, 0, 150),
            'description' => mb_substr(trim((string) Request::input('description', '')), 0, 500) ?: null,
            'icon' => $icon !== '' ? mb_substr($icon, 0, 4) : '🤝',
            'color' => $color,
            'manager_id' => (int) Request::input('manager_id', 0) ?: null,
        ];
    }

    /** أعضاء يُضافون مباشرة من نموذج الإنشاء. */
    private function syncMembersFromRequest(int $workspaceId, int $companyId, string $workspaceName): void
    {
        $ids = array_map('intval', (array) Request::input('member_ids', []));
        if (!$ids) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::select(
            "SELECT id, name FROM users WHERE company_id = ? AND status = 'active' AND id IN ({$placeholders})",
            array_merge([$companyId], $ids)
        );
        foreach ($rows as $row) {
            if ((int) $row['id'] === Auth::id()) {
                continue;
            }
            Workspace::setMember($workspaceId, (int) $row['id'], 'member');
            Notification::send((int) $row['id'], '🤝 أُضفت إلى مساحة CRM', $workspaceName, route('/crm/w/' . $workspaceId));
        }
    }

    /** تصنيفات ومسار عمل ابتدائي حتى تكون المساحة صالحة للعمل فوراً. */
    private function seedDefaults(int $workspaceId): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ([['جهة محتملة', '#6b7280', 1], ['شريك', '#059669', 2], ['مورّد', '#d97706', 3]] as [$name, $color, $order]) {
            Database::insert('crm_categories', [
                'workspace_id' => $workspaceId,
                'name' => $name,
                'color' => $color,
                'sort_order' => $order,
                'created_at' => $now,
            ]);
        }
        $pipelineId = Database::insert('crm_pipelines', [
            'workspace_id' => $workspaceId,
            'name' => 'المسار الافتراضي',
            'is_default' => 1,
            'created_at' => $now,
        ]);
        $stages = [
            ['جديد', '#6b7280', 'open'],
            ['تم التواصل', '#2563eb', 'open'],
            ['مهتم', '#7c3aed', 'open'],
            ['تفاوض', '#d97706', 'open'],
            ['تم الاتفاق', '#059669', 'won'],
            ['لم يكتمل', '#dc2626', 'lost'],
        ];
        foreach ($stages as $i => [$name, $color, $outcome]) {
            Database::insert('crm_stages', [
                'pipeline_id' => $pipelineId,
                'name' => $name,
                'sort_order' => $i + 1,
                'color' => $color,
                'outcome' => $outcome,
                'created_at' => $now,
            ]);
        }
    }
}

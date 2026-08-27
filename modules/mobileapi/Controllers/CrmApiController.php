<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use Modules\Crm\Models\Activity;
use Modules\Crm\Models\Contact;
use Modules\Crm\Models\CrmLog;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Models\Organization;
use Modules\Crm\Models\Pipeline;
use Modules\Crm\Models\Workspace;
use Modules\Mobileapi\Support\Api;

/**
 * نقاط JSON لإدارة العلاقات (CRM) - المساحات مستقلة لا يراها إلا أعضاؤها.
 *
 * الصلاحيات طبقتان ولا نتجاوزهما:
 *   ١) crm.view للدخول للوحدة أصلاً.
 *   ٢) عضوية المساحة، ثم قدرة محددة من الاثنتي عشرة عبر Workspace::can().
 *
 * والجهات ليست ملكاً لـ CRM: هي في دليل جهات الاتصال، وهنا طبقة العلاقة فقط.
 */
class CrmApiController
{
    private const PER_PAGE = 20;

    // ---------- المساحات ----------

    /** GET /api/v1/crm/workspaces - مساحات المستخدم فقط (والمدير يرى الكل). */
    public function workspaces(): void
    {
        $companyId = $this->requireCrm();
        $includeArchived = filter_var(Api::input('archived', false), FILTER_VALIDATE_BOOLEAN);

        Api::ok([
            'workspaces' => array_map(fn ($w) => [
                'id' => (int) $w['id'],
                'name' => $w['name'],
                'description' => $w['description'] ?? null,
                'icon' => $w['icon'] ?? '🤝',
                'color' => $w['color'] ?? null,
                'status' => $w['status'],
                'my_role' => $w['my_role'] ?? 'member',
                'members_count' => (int) ($w['members_count'] ?? 0),
                'orgs_count' => (int) ($w['orgs_count'] ?? 0),
            ], Workspace::forUser($companyId, Auth::id(), $includeArchived)),
            'is_admin' => Workspace::isAdmin(),
        ]);
    }

    // ---------- عملي اليوم ----------

    /**
     * GET /api/v1/crm/today - متابعات متأخرة/اليوم/قادمة + جهات لم يبدأ التواصل
     * معها + مهام CRM المسندة لي، عبر كل مساحاتي.
     */
    public function today(): void
    {
        $companyId = $this->requireCrm();
        $userId = Auth::id();

        $spaces = Workspace::forUser($companyId, $userId);
        $ids = array_map(fn ($w) => (int) $w['id'], $spaces);

        $overdue = [];
        $todayList = [];
        $upcoming = [];
        $today = date('Y-m-d');
        $weekAhead = date('Y-m-d', strtotime('+7 days'));

        foreach (Activity::dueFollowUps($companyId, $userId, $ids) as $row) {
            $day = date('Y-m-d', strtotime((string) $row['next_action_at']));
            $payload = $this->followUpPayload($row);
            if ($day < $today) {
                $overdue[] = $payload;
            } elseif ($day === $today) {
                $todayList[] = $payload;
            } elseif ($day <= $weekAhead) {
                $upcoming[] = $payload;
            }
        }

        // جهات أُسندت لي ولم يُسجَّل عليها أي نشاط بعد.
        $untouched = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = Database::select(
                "SELECT r.id AS relation_id, r.workspace_id, r.organization_id, r.created_at,
                        o.name AS organization_name, w.name AS workspace_name, w.icon
                   FROM crm_workspace_orgs r
                   JOIN contacts_organizations o ON o.id = r.organization_id
                   JOIN crm_workspaces w ON w.id = r.workspace_id
                  WHERE r.workspace_id IN ({$placeholders}) AND r.owner_id = ?
                    AND r.last_activity_at IS NULL
                  ORDER BY r.created_at DESC LIMIT 20",
                array_merge($ids, [$userId])
            );
            $untouched = array_map(fn ($r) => [
                'relation_id' => (int) $r['relation_id'],
                'workspace_id' => (int) $r['workspace_id'],
                'workspace_name' => $r['workspace_name'] ?? '',
                'icon' => $r['icon'] ?? '🤝',
                'organization_id' => (int) $r['organization_id'],
                'organization_name' => $r['organization_name'] ?? '',
                'created_at' => $r['created_at'] ?? null,
            ], $rows);
        }

        // مهام CRM: نقرأ النوعين لأن الترحيل أعاد كتابة القديم إلى contact_org
        // بينما ما زال إنشاء المتابعة يكتب crm_org.
        $tasks = [];
        if (ModuleManager::isActive('tasks')) {
            $tasks = array_map(fn ($t) => [
                'id' => (int) $t['id'],
                'title' => $t['title'],
                'status' => $t['status'],
                'due_date' => $t['due_date'] ?? null,
                'linked_id' => isset($t['linked_id']) ? (int) $t['linked_id'] : null,
                'linked_label' => $t['linked_label'] ?? null,
            ], Database::select(
                'SELECT id, title, due_date, status, linked_id, linked_label
                   FROM tasks_tasks
                  WHERE company_id = :c AND assignee_id = :u
                    AND linked_type IN ("crm_org", "contact_org")
                    AND status NOT IN ("done", "cancelled")
                  ORDER BY due_date IS NULL, due_date LIMIT 20',
                ['c' => $companyId, 'u' => $userId]
            ));
        }

        Api::ok([
            'spaces_count' => count($spaces),
            'overdue' => $overdue,
            'today' => $todayList,
            'upcoming' => $upcoming,
            'untouched' => $untouched,
            'tasks' => $tasks,
        ]);
    }

    // ---------- جهات المساحة ----------

    /** GET /api/v1/crm/w/{id}/orgs?q=&due=&stale=&owner=&page= */
    public function organizations(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);

        $filters = [];
        $q = trim((string) Api::input('q', ''));
        if ($q !== '') {
            $filters['q'] = $q;
        }
        $owner = (int) Api::input('owner', 0);
        if ($owner > 0) {
            $filters['owner'] = $owner;
        }
        foreach (['due', 'stale'] as $flag) {
            if (filter_var(Api::input($flag, false), FILTER_VALIDATE_BOOLEAN)) {
                $filters[$flag] = 1;
            }
        }

        $page = max(1, (int) Api::input('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        Api::ok([
            'workspace' => $this->workspacePayload($workspace, $membership),
            'organizations' => array_map([$this, 'relationSummary'], Organization::inWorkspace((int) $workspace['id'], $filters, self::PER_PAGE, $offset)),
            'total' => Organization::countInWorkspace((int) $workspace['id'], $filters),
            'page' => $page,
            'per_page' => self::PER_PAGE,
        ]);
    }

    /** GET /api/v1/crm/w/{id}/orgs/{orgId} - سجل العلاقة كاملاً. */
    public function showOrg(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        [$organization, $relation] = $this->requireLinkedOrg($workspace, (int) $params['orgId'], $companyId);

        // من لا يملك مشاهدة أنشطة الآخرين يرى أنشطته فقط.
        $seesAll = Workspace::can($membership, 'activities.view_others');
        $timeline = Activity::timeline(
            (int) $workspace['id'],
            (int) $organization['id'],
            100,
            $seesAll ? null : Auth::id()
        );

        Api::ok([
            'workspace' => $this->workspacePayload($workspace, $membership),
            'organization' => [
                'id' => (int) $organization['id'],
                'name' => $organization['name'],
                'trade_name' => $organization['trade_name'] ?? null,
                'kind' => $organization['kind'] ?? null,
                'sector' => $organization['sector'] ?? null,
                'city' => $organization['city'] ?? null,
                'country' => $organization['country'] ?? null,
                'phone' => $organization['phone'] ?? null,
                'email' => $organization['email'] ?? null,
                'website' => $organization['website'] ?? null,
                'address' => $organization['address'] ?? null,
                // مسار ملف الجهة في الدليل - الجهة كيان مشترك لا تملكه CRM.
                'directory_path' => '/contacts/orgs/' . (int) $organization['id'],
            ],
            'relation' => [
                'id' => (int) $relation['id'],
                'owner_id' => isset($relation['owner_id']) && $relation['owner_id'] ? (int) $relation['owner_id'] : null,
                'relation_status' => $relation['relation_status'] ?? null,
                'notes' => $relation['notes'] ?? null,
                'last_activity_at' => $relation['last_activity_at'] ?? null,
                'next_action_at' => $relation['next_action_at'] ?? null,
            ],
            'contacts' => array_map(fn ($c) => [
                'id' => (int) $c['id'],
                'name' => $c['name'] ?? ($c['full_name'] ?? ''),
                'job_title' => $c['job_title'] ?? null,
                'department' => $c['department'] ?? null,
                'mobile' => $c['mobile'] ?? null,
                'email' => $c['email'] ?? null,
                'is_primary' => (bool) ($c['is_primary'] ?? false),
            ], Contact::forOrganization((int) $organization['id'])),
            'timeline' => array_map([$this, 'activityPayload'], $timeline),
            'opportunities' => array_map([$this, 'opportunityPayload'], Opportunity::forOrganization((int) $workspace['id'], (int) $organization['id'])),
            'members' => $this->members((int) $workspace['id']),
            'activity_types' => $this->activityTypes(),
            'sees_all_activities' => $seesAll,
        ]);
    }

    /** POST /api/v1/crm/w/{id}/orgs/{orgId} - تحديث بيانات العلاقة (لا الجهة نفسها). */
    public function updateRelation(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.edit');
        [$organization, $relation] = $this->requireLinkedOrg($workspace, (int) $params['orgId'], $companyId);

        $ownerId = (int) Api::input('owner_id', 0);
        Organization::link((int) $workspace['id'], (int) $organization['id'], [
            'owner_id' => $ownerId ?: null,
            'relation_status' => mb_substr(trim((string) Api::input('relation_status', '')), 0, 60) ?: null,
            'notes' => trim((string) Api::input('notes', '')) ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        CrmLog::add((int) $workspace['id'], 'org.update', 'organization', (int) $organization['id'], 'تحديث بيانات العلاقة من الجوال');

        Api::ok(['message' => 'حُفظت بيانات العلاقة.']);
    }

    /** POST /api/v1/crm/w/{id}/orgs/link  {organization_id} - يربط جهة من الدليل بالمساحة. */
    public function linkOrg(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.create');

        $orgId = (int) Api::input('organization_id', 0);
        $organization = $orgId > 0 ? Organization::find($orgId) : null;
        if (!$organization || (int) $organization['company_id'] !== $companyId) {
            Api::error('الجهة غير موجودة في الدليل.', 404, 'not_found');
        }
        if (Organization::relation((int) $workspace['id'], $orgId)) {
            Api::error('الجهة مرتبطة بهذه المساحة مسبقاً.', 422, 'already_linked');
        }

        Organization::link((int) $workspace['id'], $orgId, [
            'owner_id' => Auth::id(),
            'added_by' => Auth::id(),
        ]);
        CrmLog::add((int) $workspace['id'], 'org.link', 'organization', $orgId, 'ربط جهة بالمساحة من الجوال: ' . $organization['name']);

        Api::ok(['message' => 'رُبطت الجهة بالمساحة.'], 201);
    }

    /** POST /api/v1/crm/w/{id}/orgs/{orgId}/unlink - تُزال من المساحة وتبقى في الدليل. */
    public function unlinkOrg(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'orgs.delete');
        [$organization] = $this->requireLinkedOrg($workspace, (int) $params['orgId'], $companyId);

        Organization::unlink((int) $workspace['id'], (int) $organization['id']);
        CrmLog::add((int) $workspace['id'], 'org.unlink', 'organization', (int) $organization['id'], 'إزالة جهة من المساحة من الجوال');

        Api::ok(['message' => 'أُزيلت الجهة من المساحة (وتبقى في الدليل).']);
    }

    // ---------- الأنشطة والمتابعات ----------

    /**
     * POST /api/v1/crm/w/{id}/orgs/{orgId}/activities
     * {type, subject?, body?, outcome?, occurred_at?, contact_id?,
     *  next_action_at?, next_action_note?, next_action_owner?}
     * حين تُحدَّد متابعة قادمة تُنشأ مهمة حقيقية في وحدة المهام مرتبطة بالجهة.
     */
    public function storeActivity(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'activities.create');
        [$organization] = $this->requireLinkedOrg($workspace, (int) $params['orgId'], $companyId);

        $type = (string) Api::input('type', 'note');
        if (!array_key_exists($type, Activity::types())) {
            $type = 'note';
        }

        $subject = mb_substr(trim((string) Api::input('subject', '')), 0, 200);
        $body = trim((string) Api::input('body', ''));
        if ($body === '' && $subject === '') {
            Api::error('اكتب ما جرى في هذا النشاط.', 422, 'validation');
        }

        $contactId = (int) Api::input('contact_id', 0);
        if ($contactId > 0 && !Contact::belongsTo($contactId, (int) $organization['id'])) {
            $contactId = 0;
        }

        $occurredAt = trim((string) Api::input('occurred_at', ''));
        $nextActionAt = trim((string) Api::input('next_action_at', ''));
        $nextOwner = (int) Api::input('next_action_owner', 0);

        $activityId = Activity::log([
            'workspace_id' => (int) $workspace['id'],
            'organization_id' => (int) $organization['id'],
            'contact_id' => $contactId ?: null,
            'type' => $type,
            'subject' => $subject ?: null,
            'body' => $body ?: null,
            'outcome' => mb_substr(trim((string) Api::input('outcome', '')), 0, 255) ?: null,
            'occurred_at' => $occurredAt !== '' ? date('Y-m-d H:i:s', strtotime($occurredAt)) : date('Y-m-d H:i:s'),
            'next_action_at' => $nextActionAt !== '' ? date('Y-m-d H:i:s', strtotime($nextActionAt)) : null,
            'next_action_note' => mb_substr(trim((string) Api::input('next_action_note', '')), 0, 255) ?: null,
        ], $workspace, $organization, $nextOwner ?: null);

        CrmLog::add((int) $workspace['id'], 'activity.create', 'organization', (int) $organization['id'], 'تسجيل نشاط من الجوال (' . Activity::typeLabel($type) . ')');

        Api::ok([
            'id' => $activityId,
            'message' => $nextActionAt !== ''
                ? 'سُجّل النشاط وأُنشئت مهمة متابعة في ' . date('Y-m-d', strtotime($nextActionAt)) . '.'
                : 'سُجّل النشاط.',
        ], 201);
    }

    /** POST /api/v1/crm/w/{id}/activities/{activityId}/done - إنهاء المتابعة ومهمتها. */
    public function completeFollowUp(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'activities.create');
        $activity = $this->requireActivity((int) $params['activityId'], (int) $workspace['id']);

        Activity::completeFollowUp((int) $activity['id']);
        CrmLog::add((int) $workspace['id'], 'activity.followup_done', 'organization', (int) $activity['organization_id'], 'إنهاء متابعة من الجوال');

        Api::ok(['message' => 'أُنهيت المتابعة.']);
    }

    /** POST /api/v1/crm/w/{id}/activities/{activityId}/delete - لكاتبه أو لمدير المساحة. */
    public function deleteActivity(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $activity = $this->requireActivity((int) $params['activityId'], (int) $workspace['id']);

        if ((int) $activity['user_id'] !== Auth::id() && ($membership['role'] ?? '') !== 'manager') {
            Api::error('يمكن حذف النشاط لكاتبه أو لمدير المساحة فقط.', 403, 'forbidden');
        }

        Database::delete('crm_activities', 'id = :id', ['id' => (int) $activity['id']]);
        CrmLog::add((int) $workspace['id'], 'activity.delete', 'organization', (int) $activity['organization_id'], 'حذف نشاط من الجوال');

        Api::ok(['message' => 'حُذف النشاط.']);
    }

    // ---------- الفرص ----------

    /** GET /api/v1/crm/w/{id}/opportunities?pipeline=&owner=&q=&closed= */
    public function opportunities(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);

        $pipelines = Pipeline::forWorkspace((int) $workspace['id']);
        if (!$pipelines) {
            Api::ok([
                'workspace' => $this->workspacePayload($workspace, $membership),
                'pipelines' => [],
                'pipeline' => null,
                'stages' => [],
                'stats' => null,
                'message' => 'لا مسار عمل في هذه المساحة بعد — يُنشأ من الويب.',
            ]);
        }

        $requested = (int) Api::input('pipeline', 0);
        $pipeline = null;
        foreach ($pipelines as $row) {
            if ((int) $row['id'] === $requested) {
                $pipeline = $row;
                break;
            }
        }
        $pipeline = $pipeline ?: $pipelines[0];

        $filters = [];
        $q = trim((string) Api::input('q', ''));
        if ($q !== '') {
            $filters['q'] = $q;
        }
        $owner = (int) Api::input('owner', 0);
        if ($owner > 0) {
            $filters['owner'] = $owner;
        }
        if (filter_var(Api::input('closed', false), FILTER_VALIDATE_BOOLEAN)) {
            $filters['include_closed'] = 1;
        }

        $grouped = Opportunity::byStage((int) $workspace['id'], (int) $pipeline['id'], $filters);
        $stages = [];
        foreach (Pipeline::stages((int) $pipeline['id']) as $stage) {
            $stages[] = [
                'id' => (int) $stage['id'],
                'name' => $stage['name'],
                'color' => $stage['color'] ?? null,
                'outcome' => $stage['outcome'],
                'opportunities' => array_map([$this, 'opportunityPayload'], $grouped[(int) $stage['id']] ?? []),
            ];
        }

        Api::ok([
            'workspace' => $this->workspacePayload($workspace, $membership),
            'pipelines' => array_map(fn ($p) => [
                'id' => (int) $p['id'],
                'name' => $p['name'],
                'is_default' => (bool) ($p['is_default'] ?? false),
            ], $pipelines),
            'pipeline' => ['id' => (int) $pipeline['id'], 'name' => $pipeline['name']],
            'stages' => $stages,
            'stats' => Opportunity::stats((int) $workspace['id']),
            'members' => $this->members((int) $workspace['id']),
        ]);
    }

    /** POST /api/v1/crm/w/{id}/opportunities  {name, organization_id, ...} */
    public function storeOpportunity(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'opportunities.create');

        $name = trim((string) Api::input('name', ''));
        if ($name === '') {
            Api::error('اسم الفرصة مطلوب.', 422, 'validation');
        }

        $orgId = (int) Api::input('organization_id', 0);
        if (!$orgId || !Organization::relation((int) $workspace['id'], $orgId)) {
            Api::error('اختر جهة مرتبطة بهذه المساحة.', 422, 'validation');
        }

        $pipelineId = (int) Api::input('pipeline_id', 0);
        if (!$pipelineId) {
            $default = Pipeline::defaultFor((int) $workspace['id']);
            $pipelineId = $default ? (int) $default['id'] : 0;
        }
        if (!$pipelineId) {
            Api::error('لا مسار عمل في هذه المساحة بعد.', 422, 'no_pipeline');
        }

        $stageId = (int) Api::input('stage_id', 0);
        $stage = $stageId ? Pipeline::findStage($stageId) : null;
        if (!$stage || (int) $stage['pipeline_id'] !== $pipelineId) {
            $stages = Pipeline::stages($pipelineId);
            $stage = $stages[0] ?? null;
        }

        $contactId = (int) Api::input('contact_id', 0);
        if ($contactId > 0 && !Contact::belongsTo($contactId, $orgId)) {
            $contactId = 0;
        }
        $ownerId = (int) Api::input('owner_id', 0) ?: Auth::id();
        $value = Api::input('value');

        $oppId = Opportunity::create([
            'workspace_id' => (int) $workspace['id'],
            'organization_id' => $orgId,
            'contact_id' => $contactId ?: null,
            'pipeline_id' => $pipelineId,
            'stage_id' => $stage ? (int) $stage['id'] : null,
            'name' => mb_substr($name, 0, 200),
            'owner_id' => $ownerId,
            'value' => is_numeric($value) ? (float) $value : null,
            'probability' => max(0, min(100, (int) Api::input('probability', 0))) ?: null,
            'expected_close_date' => trim((string) Api::input('expected_close_date', '')) ?: null,
            'source' => mb_substr(trim((string) Api::input('source', '')), 0, 120) ?: null,
            'description' => trim((string) Api::input('description', '')) ?: null,
            'status' => $stage['outcome'] ?? 'open',
            'created_by' => Auth::id(),
        ]);

        CrmLog::add((int) $workspace['id'], 'opportunity.create', 'opportunity', $oppId, 'إنشاء فرصة من الجوال: ' . $name);
        Api::ok(['id' => $oppId, 'message' => 'أُنشئت الفرصة.'], 201);
    }

    /** POST /api/v1/crm/w/{id}/opportunities/{oppId}/move  {stage_id} */
    public function moveOpportunity(array $params): void
    {
        $companyId = $this->requireCrm();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'opportunities.edit');

        $opportunity = Opportunity::find((int) $params['oppId']);
        if (!$opportunity || (int) $opportunity['workspace_id'] !== (int) $workspace['id']) {
            Api::error('الفرصة غير موجودة.', 404, 'not_found');
        }

        $stage = Pipeline::findStage((int) Api::input('stage_id', 0));
        if (!$stage || (int) $stage['pipeline_id'] !== (int) $opportunity['pipeline_id']) {
            Api::error('المرحلة غير صحيحة.', 422, 'validation');
        }

        $previous = $opportunity['stage_id'] ? Pipeline::findStage((int) $opportunity['stage_id']) : null;
        Opportunity::update((int) $opportunity['id'], [
            'stage_id' => (int) $stage['id'],
            'status' => $stage['outcome'],
            'closed_at' => $stage['outcome'] === 'open' ? null : date('Y-m-d H:i:s'),
        ]);

        // النقل يُسجَّل نشاطاً في سجل العلاقة تلقائياً - كما في الويب.
        $organization = Organization::find((int) $opportunity['organization_id']);
        if ($organization) {
            Activity::log([
                'workspace_id' => (int) $workspace['id'],
                'organization_id' => (int) $organization['id'],
                'opportunity_id' => (int) $opportunity['id'],
                'type' => 'stage_change',
                'subject' => $opportunity['name'],
                'body' => 'نُقلت الفرصة من «' . ($previous['name'] ?? 'بلا مرحلة') . '» إلى «' . $stage['name'] . '».',
            ], $workspace, $organization);
        }
        CrmLog::add((int) $workspace['id'], 'opportunity.stage', 'opportunity', (int) $opportunity['id'], 'نقل الفرصة إلى ' . $stage['name']);

        Api::ok(['status' => $stage['outcome'], 'stage' => $stage['name']]);
    }

    // ---------- طبقتا الصلاحيات ----------

    private function requireCrm(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            Api::error('حسابك غير مرتبط بشركة.', 422, 'no_company');
        }
        Api::requirePermission('crm.view');
        return $companyId;
    }

    /** @return array{0: array, 1: array} */
    private function requireWorkspace(int $workspaceId, int $companyId): array
    {
        $workspace = $workspaceId > 0 ? Workspace::find($workspaceId) : null;
        if (!$workspace || (int) $workspace['company_id'] !== $companyId) {
            Api::error('المساحة غير موجودة.', 404, 'not_found');
        }

        $membership = Workspace::membership($workspaceId, Auth::id());
        if (!$membership) {
            Api::error('لست عضواً في هذه المساحة.', 403, 'forbidden');
        }

        return [$workspace, $membership];
    }

    private function requireAbility(array $membership, string $ability): void
    {
        if (!Workspace::can($membership, $ability)) {
            Api::error('لا تملك هذه الصلاحية داخل المساحة.', 403, 'forbidden');
        }
    }

    /** @return array{0: array, 1: array} */
    private function requireLinkedOrg(array $workspace, int $orgId, int $companyId): array
    {
        $organization = $orgId > 0 ? Organization::find($orgId) : null;
        if (!$organization || (int) $organization['company_id'] !== $companyId) {
            Api::error('الجهة غير موجودة.', 404, 'not_found');
        }
        $relation = Organization::relation((int) $workspace['id'], $orgId);
        if (!$relation) {
            Api::error('الجهة غير مرتبطة بهذه المساحة.', 404, 'not_linked');
        }
        return [$organization, $relation];
    }

    private function requireActivity(int $activityId, int $workspaceId): array
    {
        $activity = $activityId > 0 ? Activity::find($activityId) : null;
        if (!$activity || (int) $activity['workspace_id'] !== $workspaceId) {
            Api::error('النشاط غير موجود.', 404, 'not_found');
        }
        return $activity;
    }

    // ---------- التسلسل ----------

    private function workspacePayload(array $workspace, array $membership): array
    {
        return [
            'id' => (int) $workspace['id'],
            'name' => $workspace['name'],
            'icon' => $workspace['icon'] ?? '🤝',
            'color' => $workspace['color'] ?? null,
            'status' => $workspace['status'],
            'my_role' => $membership['role'] ?? 'viewer',
            // قدراتي هنا: التطبيق يخفي ما لا أملكه بدل أن يفشل عند الإرسال.
            'abilities' => Workspace::abilitiesOf($membership),
        ];
    }

    private function relationSummary(array $r): array
    {
        return [
            'relation_id' => (int) $r['id'],
            'organization_id' => (int) $r['organization_id'],
            'name' => $r['name'] ?? '',
            'trade_name' => $r['trade_name'] ?? null,
            'kind' => $r['kind'] ?? null,
            'sector' => $r['sector'] ?? null,
            'city' => $r['city'] ?? null,
            'phone' => $r['phone'] ?? null,
            'email' => $r['email'] ?? null,
            'owner_id' => isset($r['owner_id']) && $r['owner_id'] ? (int) $r['owner_id'] : null,
            'owner_name' => $r['owner_name'] ?? null,
            'relation_status' => $r['relation_status'] ?? null,
            'categories' => $r['categories'] ?? null,
            'last_activity_at' => $r['last_activity_at'] ?? null,
            'next_action_at' => $r['next_action_at'] ?? null,
        ];
    }

    private function followUpPayload(array $row): array
    {
        return [
            'activity_id' => (int) $row['id'],
            'workspace_id' => (int) $row['workspace_id'],
            'workspace_name' => $row['workspace_name'] ?? '',
            'icon' => $row['icon'] ?? '🤝',
            'organization_id' => (int) $row['organization_id'],
            'organization_name' => $row['organization_name'] ?? '',
            'next_action_at' => $row['next_action_at'],
            'next_action_note' => $row['next_action_note'] ?? null,
            'task_id' => isset($row['task_id']) && $row['task_id'] ? (int) $row['task_id'] : null,
        ];
    }

    private function activityPayload(array $a): array
    {
        return [
            'id' => (int) $a['id'],
            'type' => $a['type'],
            'type_label' => Activity::typeLabel((string) $a['type']),
            'type_icon' => Activity::typeIcon((string) $a['type']),
            'subject' => $a['subject'] ?? null,
            'body' => $a['body'] ?? null,
            'outcome' => $a['outcome'] ?? null,
            'occurred_at' => $a['occurred_at'],
            'user_id' => (int) $a['user_id'],
            'user_name' => $a['user_name'] ?? '',
            'contact_name' => $a['contact_name'] ?? null,
            'next_action_at' => $a['next_action_at'] ?? null,
            'next_action_note' => $a['next_action_note'] ?? null,
            'next_action_status' => $a['next_action_status'] ?? 'none',
            'task_id' => isset($a['task_id']) && $a['task_id'] ? (int) $a['task_id'] : null,
        ];
    }

    private function opportunityPayload(array $o): array
    {
        return [
            'id' => (int) $o['id'],
            'name' => $o['name'],
            'organization_id' => (int) $o['organization_id'],
            'organization_name' => $o['organization_name'] ?? ($o['org_name'] ?? null),
            'stage_id' => isset($o['stage_id']) && $o['stage_id'] ? (int) $o['stage_id'] : null,
            'stage_name' => $o['stage_name'] ?? null,
            'status' => $o['status'],
            'value' => isset($o['value']) && $o['value'] !== null ? (float) $o['value'] : null,
            'probability' => isset($o['probability']) && $o['probability'] !== null ? (int) $o['probability'] : null,
            'expected_close_date' => $o['expected_close_date'] ?? null,
            'owner_id' => isset($o['owner_id']) && $o['owner_id'] ? (int) $o['owner_id'] : null,
            'owner_name' => $o['owner_name'] ?? null,
            'contact_name' => $o['contact_name'] ?? null,
        ];
    }

    private function members(int $workspaceId): array
    {
        return array_map(fn ($m) => [
            'user_id' => (int) $m['user_id'],
            'name' => $m['name'] ?? '',
            'role' => $m['role'],
        ], Workspace::members($workspaceId));
    }

    private function activityTypes(): array
    {
        $types = [];
        foreach (Activity::types() as $key => $meta) {
            if ($key === 'stage_change') {
                continue; // يُسجَّل تلقائياً عند نقل الفرصة، لا يختاره المستخدم.
            }
            $types[] = [
                'key' => (string) $key,
                'icon' => $meta[0] ?? '',
                'label' => $meta[1] ?? (string) $key,
            ];
        }
        return $types;
    }
}

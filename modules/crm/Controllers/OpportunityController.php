<?php

namespace Modules\Crm\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Notification;
use App\Core\Request;
use App\Core\View;
use Modules\Crm\Models\Activity;
use Modules\Crm\Models\Contact;
use Modules\Crm\Models\CrmLog;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Models\Organization;
use Modules\Crm\Models\Pipeline;
use Modules\Crm\Models\Workspace;

/** الفرص وعرضها على المراحل (Kanban) داخل المساحة. */
class OpportunityController extends BaseCrmController
{
    /** لوحة الفرص: أعمدة المراحل وبطاقات الفرص. */
    public function index(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);

        $pipelines = Pipeline::forWorkspace((int) $workspace['id']);
        if (!$pipelines) {
            flash_set('error', 'لا يوجد مسار عمل في هذه المساحة — أنشئه من الإعدادات.');
            redirect('/crm/w/' . $workspace['id'] . '/pipelines');
        }
        $pipelineId = (int) Request::query('pipeline', 0);
        $pipeline = $pipelineId ? Pipeline::find($pipelineId) : null;
        if (!$pipeline || (int) $pipeline['workspace_id'] !== (int) $workspace['id']) {
            $pipeline = $pipelines[0];
        }

        $filters = [
            'owner' => (int) Request::query('owner', 0),
            'q' => trim((string) Request::query('q', '')),
            'include_closed' => Request::query('closed') ? 1 : 0,
        ];

        View::render('crm::opportunities.board', [
            'pageTitle' => 'الفرص — ' . $workspace['name'],
            'workspace' => $workspace,
            'membership' => $membership,
            'pipelines' => $pipelines,
            'pipeline' => $pipeline,
            'stages' => Pipeline::stages((int) $pipeline['id']),
            'grouped' => Opportunity::byStage((int) $workspace['id'], (int) $pipeline['id'], $filters),
            'stats' => Opportunity::stats((int) $workspace['id']),
            'members' => Workspace::members((int) $workspace['id']),
            'filters' => $filters,
            'canCreate' => Workspace::can($membership, 'opportunities.create'),
            'canEdit' => Workspace::can($membership, 'opportunities.edit'),
            'canManagePipeline' => Workspace::can($membership, 'pipeline.manage'),
        ]);
    }

    public function create(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'opportunities.create');

        $orgId = (int) Request::query('org', 0);
        View::render('crm::opportunities.form', [
            'pageTitle' => 'فرصة جديدة',
            'workspace' => $workspace,
            'opportunity' => null,
            'organizations' => Organization::inWorkspace((int) $workspace['id'], [], 500),
            'selectedOrg' => $orgId,
            'contacts' => $orgId ? Contact::forOrganization($orgId) : [],
            'pipelines' => Pipeline::forWorkspace((int) $workspace['id']),
            'stagesByPipeline' => $this->stagesByPipeline((int) $workspace['id']),
            'members' => Workspace::members((int) $workspace['id']),
        ]);
    }

    public function store(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'opportunities.create');
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/opportunities/create');

        $data = $this->validated($workspace, $companyId);
        if ($data === null) {
            redirect('/crm/w/' . $workspace['id'] . '/opportunities/create');
        }

        $opportunityId = Opportunity::create($data + [
            'workspace_id' => (int) $workspace['id'],
            'created_by' => Auth::id(),
        ]);

        $organization = Organization::find((int) $data['organization_id']);
        CrmLog::add((int) $workspace['id'], 'opportunity.create', 'opportunity', $opportunityId,
            'إنشاء فرصة: ' . $data['name'] . ' — ' . ($organization['name'] ?? ''));
        if (!empty($data['owner_id']) && (int) $data['owner_id'] !== Auth::id()) {
            Notification::send((int) $data['owner_id'], '💼 أُسندت إليك فرصة',
                $data['name'], route('/crm/w/' . $workspace['id'] . '/opportunities/' . $opportunityId));
        }
        flash_set('success', 'أُنشئت الفرصة.');
        redirect('/crm/w/' . $workspace['id'] . '/opportunities/' . $opportunityId);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $opportunity = $this->requireOpportunity($workspace, (int) $params['oppId']);
        $organization = Organization::find((int) $opportunity['organization_id']);

        View::render('crm::opportunities.show', [
            'pageTitle' => $opportunity['name'],
            'workspace' => $workspace,
            'membership' => $membership,
            'opportunity' => $opportunity,
            'organization' => $organization,
            'organizations' => Organization::inWorkspace((int) $workspace['id'], [], 500),
            'contacts' => Contact::forOrganization((int) $opportunity['organization_id']),
            'pipelines' => Pipeline::forWorkspace((int) $workspace['id']),
            'stagesByPipeline' => $this->stagesByPipeline((int) $workspace['id']),
            'stages' => Pipeline::stages((int) $opportunity['pipeline_id']),
            'members' => Workspace::members((int) $workspace['id']),
            'activities' => Workspace::can($membership, 'activities.view_others')
                ? Database::select(
                    'SELECT a.*, u.name AS user_name FROM crm_activities a LEFT JOIN users u ON u.id = a.user_id
                      WHERE a.opportunity_id = :o ORDER BY a.occurred_at DESC LIMIT 50',
                    ['o' => $opportunity['id']]
                )
                : Database::select(
                    'SELECT a.*, u.name AS user_name FROM crm_activities a LEFT JOIN users u ON u.id = a.user_id
                      WHERE a.opportunity_id = :o AND a.user_id = :me ORDER BY a.occurred_at DESC LIMIT 50',
                    ['o' => $opportunity['id'], 'me' => Auth::id()]
                ),
            'logs' => CrmLog::forEntity('opportunity', (int) $opportunity['id'], 15),
            'canEdit' => Workspace::can($membership, 'opportunities.edit'),
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'opportunities.edit');
        $opportunity = $this->requireOpportunity($workspace, (int) $params['oppId']);
        $back = '/crm/w/' . $workspace['id'] . '/opportunities/' . $opportunity['id'];
        $this->verifyCsrf($back);

        $data = $this->validated($workspace, $companyId, $opportunity);
        if ($data === null) {
            redirect($back);
        }
        Opportunity::update((int) $opportunity['id'], $data);
        CrmLog::add((int) $workspace['id'], 'opportunity.update', 'opportunity', (int) $opportunity['id'], 'تعديل الفرصة');
        flash_set('success', 'حُفظت الفرصة.');
        redirect($back);
    }

    /**
     * نقل فرصة إلى مرحلة - من اللوحة (سحب وإفلات) أو من صفحة الفرصة.
     * كل نقلة تُسجَّل في سجل علاقة الجهة كنشاط، فيبقى تاريخ الفرصة مقروءاً.
     */
    public function move(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $isAjax = Request::input('ajax') || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

        if (!Workspace::can($membership, 'opportunities.edit')) {
            if ($isAjax) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'forbidden']);
                exit;
            }
            $this->forbidden();
        }
        $opportunity = $this->requireOpportunity($workspace, (int) $params['oppId']);
        $back = '/crm/w/' . $workspace['id'] . '/opportunities?pipeline=' . $opportunity['pipeline_id'];

        if (!\App\Core\Csrf::verify(Request::input('_csrf'))) {
            if ($isAjax) {
                http_response_code(419);
                echo json_encode(['ok' => false, 'error' => 'csrf']);
                exit;
            }
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }

        $stage = Pipeline::findStage((int) Request::input('stage_id', 0));
        if (!$stage || (int) $stage['pipeline_id'] !== (int) $opportunity['pipeline_id']) {
            if ($isAjax) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'stage']);
                exit;
            }
            flash_set('error', 'المرحلة غير صالحة.');
            redirect($back);
        }

        $previous = $opportunity['stage_id'] ? Pipeline::findStage((int) $opportunity['stage_id']) : null;
        $update = ['stage_id' => (int) $stage['id'], 'status' => $stage['outcome']];
        $update['closed_at'] = $stage['outcome'] === 'open' ? null : date('Y-m-d H:i:s');
        Opportunity::update((int) $opportunity['id'], $update);

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
        CrmLog::add((int) $workspace['id'], 'opportunity.stage', 'opportunity', (int) $opportunity['id'],
            'نقل الفرصة إلى مرحلة: ' . $stage['name']);

        if ($isAjax) {
            echo json_encode(['ok' => true, 'status' => $stage['outcome'], 'stage' => $stage['name']]);
            exit;
        }
        flash_set('success', 'نُقلت الفرصة إلى «' . $stage['name'] . '».');
        redirect($back);
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'opportunities.edit');
        $opportunity = $this->requireOpportunity($workspace, (int) $params['oppId']);
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/opportunities/' . $opportunity['id']);

        Opportunity::delete((int) $opportunity['id']);
        CrmLog::add((int) $workspace['id'], 'opportunity.delete', 'opportunity', (int) $opportunity['id'],
            'حذف الفرصة: ' . $opportunity['name']);
        flash_set('success', 'حُذفت الفرصة.');
        redirect('/crm/w/' . $workspace['id'] . '/opportunities');
    }

    // ---------------------------------------------------------------

    private function requireOpportunity(array $workspace, int $oppId): array
    {
        $opportunity = Opportunity::find($oppId);
        if (!$opportunity || (int) $opportunity['workspace_id'] !== (int) $workspace['id']) {
            flash_set('error', 'الفرصة غير موجودة في هذه المساحة.');
            redirect('/crm/w/' . $workspace['id'] . '/opportunities');
        }
        return $opportunity;
    }

    private function stagesByPipeline(int $workspaceId): array
    {
        $out = [];
        foreach (Pipeline::forWorkspace($workspaceId) as $p) {
            $out[(int) $p['id']] = Pipeline::stages((int) $p['id']);
        }
        return $out;
    }

    private function validated(array $workspace, int $companyId, ?array $existing = null): ?array
    {
        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم الفرصة مطلوب.');
            return null;
        }
        $organizationId = (int) Request::input('organization_id', 0);
        if (!Organization::relation((int) $workspace['id'], $organizationId)) {
            flash_set('error', 'اختر جهة مرتبطة بهذه المساحة.');
            return null;
        }
        $pipeline = Pipeline::find((int) Request::input('pipeline_id', 0));
        if (!$pipeline || (int) $pipeline['workspace_id'] !== (int) $workspace['id']) {
            $pipeline = Pipeline::defaultFor((int) $workspace['id']);
        }
        if (!$pipeline) {
            flash_set('error', 'لا يوجد مسار عمل في هذه المساحة.');
            return null;
        }
        $stage = Pipeline::findStage((int) Request::input('stage_id', 0));
        if (!$stage || (int) $stage['pipeline_id'] !== (int) $pipeline['id']) {
            $stages = Pipeline::stages((int) $pipeline['id']);
            $stage = $stages[0] ?? null;
        }

        $contactId = (int) Request::input('contact_id', 0) ?: null;
        if ($contactId && !Contact::belongsTo($contactId, $organizationId)) {
            $contactId = null;
        }
        $probability = Request::input('probability', '');
        $value = Request::input('value', '');

        return [
            'name' => mb_substr($name, 0, 200),
            'organization_id' => $organizationId,
            'contact_id' => $contactId,
            'pipeline_id' => (int) $pipeline['id'],
            'stage_id' => $stage ? (int) $stage['id'] : null,
            'status' => $stage ? $stage['outcome'] : 'open',
            'owner_id' => (int) Request::input('owner_id', 0) ?: Auth::id(),
            'value' => $value !== '' ? (float) $value : null,
            'probability' => $probability !== '' ? max(0, min(100, (int) $probability)) : null,
            'expected_close_date' => Request::input('expected_close_date') ?: null,
            'source' => mb_substr(trim((string) Request::input('source', '')), 0, 120) ?: null,
            'description' => trim((string) Request::input('description', '')) ?: null,
        ];
    }
}

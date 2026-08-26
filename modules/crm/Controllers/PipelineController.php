<?php

namespace Modules\Crm\Controllers;

use App\Core\Request;
use App\Core\View;
use Modules\Crm\Models\CrmLog;
use Modules\Crm\Models\Pipeline;
use Modules\Crm\Models\Workspace;

/** إدارة مسارات العمل ومراحلها - لمن يملك «إدارة مراحل العمل» في المساحة. */
class PipelineController extends BaseCrmController
{
    public function index(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'pipeline.manage');

        $pipelines = Pipeline::forWorkspace((int) $workspace['id']);
        $stages = [];
        foreach ($pipelines as $p) {
            $stages[(int) $p['id']] = Pipeline::stages((int) $p['id'], false);
        }

        View::render('crm::pipelines', [
            'pageTitle' => 'مراحل العمل — ' . $workspace['name'],
            'workspace' => $workspace,
            'pipelines' => $pipelines,
            'stages' => $stages,
        ]);
    }

    public function storePipeline(array $params): void
    {
        [$workspace, ] = $this->guard($params);
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/pipelines');

        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم المسار مطلوب.');
            redirect('/crm/w/' . $workspace['id'] . '/pipelines');
        }
        $pipelineId = Pipeline::create((int) $workspace['id'], $name, (bool) Request::input('is_default'));
        // مراحل ابتدائية حتى يكون المسار صالحاً للعمل فوراً
        foreach ([['جديد', '#6b7280', 'open'], ['قيد العمل', '#2563eb', 'open'], ['تم', '#059669', 'won'], ['لم يكتمل', '#dc2626', 'lost']] as [$n, $c, $o]) {
            Pipeline::addStage($pipelineId, $n, $c, $o);
        }
        CrmLog::add((int) $workspace['id'], 'pipeline.create', 'workspace', (int) $workspace['id'], 'إنشاء مسار: ' . $name);
        flash_set('success', 'أُنشئ المسار بمراحل ابتدائية — عدّلها كما تشاء.');
        redirect('/crm/w/' . $workspace['id'] . '/pipelines');
    }

    public function storeStage(array $params): void
    {
        [$workspace, ] = $this->guard($params);
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/pipelines');

        $pipeline = Pipeline::find((int) Request::input('pipeline_id', 0));
        if (!$pipeline || (int) $pipeline['workspace_id'] !== (int) $workspace['id']) {
            flash_set('error', 'المسار غير موجود.');
            redirect('/crm/w/' . $workspace['id'] . '/pipelines');
        }
        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم المرحلة مطلوب.');
            redirect('/crm/w/' . $workspace['id'] . '/pipelines');
        }
        $color = (string) Request::input('color', '#6b7280');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6b7280';
        }
        Pipeline::addStage((int) $pipeline['id'], $name, $color, (string) Request::input('outcome', 'open'));
        CrmLog::add((int) $workspace['id'], 'stage.create', 'workspace', (int) $workspace['id'], 'إضافة مرحلة: ' . $name);
        flash_set('success', 'أُضيفت المرحلة.');
        redirect('/crm/w/' . $workspace['id'] . '/pipelines');
    }

    public function updateStage(array $params): void
    {
        [$workspace, ] = $this->guard($params);
        $this->verifyCsrf('/crm/w/' . $workspace['id'] . '/pipelines');
        $stage = $this->requireStage($workspace, (int) $params['stageId']);

        $action = (string) Request::input('action', 'save');
        if ($action === 'up' || $action === 'down') {
            Pipeline::moveStage((int) $stage['id'], $action);
            redirect('/crm/w/' . $workspace['id'] . '/pipelines');
        }
        if ($action === 'toggle') {
            Pipeline::updateStage((int) $stage['id'], ['is_active' => $stage['is_active'] ? 0 : 1]);
            CrmLog::add((int) $workspace['id'], 'stage.toggle', 'workspace', (int) $workspace['id'],
                ($stage['is_active'] ? 'تعطيل' : 'تفعيل') . ' مرحلة: ' . $stage['name']);
            redirect('/crm/w/' . $workspace['id'] . '/pipelines');
        }

        $name = trim((string) Request::input('name', ''));
        $color = (string) Request::input('color', $stage['color']);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = $stage['color'];
        }
        Pipeline::updateStage((int) $stage['id'], [
            'name' => $name !== '' ? mb_substr($name, 0, 120) : $stage['name'],
            'color' => $color,
            'outcome' => in_array(Request::input('outcome'), ['open', 'won', 'lost'], true) ? Request::input('outcome') : $stage['outcome'],
        ]);
        CrmLog::add((int) $workspace['id'], 'stage.update', 'workspace', (int) $workspace['id'], 'تعديل مرحلة: ' . $stage['name']);
        flash_set('success', 'حُفظت المرحلة.');
        redirect('/crm/w/' . $workspace['id'] . '/pipelines');
    }

    // ---------------------------------------------------------------

    private function guard(array $params): array
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'pipeline.manage');
        return [$workspace, $membership];
    }

    private function requireStage(array $workspace, int $stageId): array
    {
        $stage = Pipeline::findStage($stageId);
        $pipeline = $stage ? Pipeline::find((int) $stage['pipeline_id']) : null;
        if (!$stage || !$pipeline || (int) $pipeline['workspace_id'] !== (int) $workspace['id']) {
            flash_set('error', 'المرحلة غير موجودة.');
            redirect('/crm/w/' . $workspace['id'] . '/pipelines');
        }
        return $stage;
    }
}

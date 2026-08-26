<?php

namespace Modules\Crm\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use Modules\Crm\Models\CrmLog;
use Modules\Crm\Models\Pipeline;
use Modules\Crm\Models\Stats;
use Modules\Crm\Models\Workspace;

/** لوحة المساحة وسجل تغييراتها. */
class DashboardController extends BaseCrmController
{
    public function index(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'reports.view');

        $filters = [
            'from' => Request::query('from') ?: date('Y-m-01'),
            'to' => Request::query('to') ?: date('Y-m-d'),
            'owner' => (int) Request::query('owner', 0),
            'category' => (int) Request::query('category', 0),
            'pipeline' => (int) Request::query('pipeline', 0),
        ];
        if (!Workspace::can($membership, 'activities.view_others')) {
            $filters['activity_user'] = \App\Core\Auth::id();
        }

        View::render('crm::dashboard', [
            'pageTitle' => 'لوحة ' . $workspace['name'],
            'workspace' => $workspace,
            'membership' => $membership,
            'stats' => Stats::workspace((int) $workspace['id'], $filters),
            'filters' => $filters,
            'members' => Workspace::members((int) $workspace['id']),
            'seesAllActivities' => Workspace::can($membership, 'activities.view_others'),
            'categories' => Database::select('SELECT * FROM crm_categories WHERE workspace_id = :w ORDER BY sort_order, name', ['w' => $workspace['id']]),
            'pipelines' => Pipeline::forWorkspace((int) $workspace['id']),
        ]);
    }

    /** سجل التغييرات: من فعل ماذا ومتى داخل المساحة. */
    public function logs(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        [$workspace, $membership] = $this->requireWorkspace((int) $params['id'], $companyId);
        $this->requireAbility($membership, 'settings.manage');

        View::render('crm::logs', [
            'pageTitle' => 'سجل التغييرات — ' . $workspace['name'],
            'workspace' => $workspace,
            'logs' => CrmLog::forWorkspace((int) $workspace['id'], 200),
        ]);
    }
}

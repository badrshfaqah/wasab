<?php

namespace Modules\Crm\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\View;
use Modules\Crm\Models\Activity;
use Modules\Crm\Models\Workspace;

/**
 * «عملي اليوم»: ما على الموظف فعله الآن عبر كل مساحاته - متابعات مستحقة
 * ومتأخرة، جهات بلا تواصل، ومهام CRM المفتوحة في إضافة المهام.
 */
class TodayController extends BaseCrmController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        $userId = Auth::id();
        $spaces = Workspace::forUser($companyId, $userId);
        $ids = array_map(fn ($w) => (int) $w['id'], $spaces);

        $overdue = [];
        $today = [];
        $upcoming = [];
        foreach (Activity::dueFollowUps($companyId, $userId, $ids) as $row) {
            $date = date('Y-m-d', strtotime($row['next_action_at']));
            if ($date < date('Y-m-d')) {
                $overdue[] = $row;
            } elseif ($date === date('Y-m-d')) {
                $today[] = $row;
            } elseif ($date <= date('Y-m-d', strtotime('+7 days'))) {
                $upcoming[] = $row;
            }
        }

        // جهات مسؤول عنها ولم يبدأ التواصل معها بعد
        $untouched = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $untouched = Database::select(
                "SELECT r.*, o.name AS organization_name, w.name AS workspace_name, w.icon
                   FROM crm_workspace_orgs r
                   JOIN crm_organizations o ON o.id = r.organization_id
                   JOIN crm_workspaces w ON w.id = r.workspace_id
                  WHERE r.workspace_id IN ({$placeholders}) AND r.owner_id = ?
                    AND r.last_activity_at IS NULL
                  ORDER BY r.created_at DESC LIMIT 20",
                array_merge($ids, [$userId])
            );
        }

        // مهام CRM المفتوحة (المصدر: إضافة المهام نفسها لا نسخة منها)
        $tasks = [];
        if (ModuleManager::isActive('tasks')) {
            $tasks = Database::select(
                "SELECT id, title, due_date, status, linked_id, linked_label
                   FROM tasks_tasks
                  WHERE company_id = :c AND assignee_id = :u AND linked_type = 'crm_org'
                    AND status NOT IN ('done', 'cancelled')
                  ORDER BY due_date IS NULL, due_date LIMIT 20",
                ['c' => $companyId, 'u' => $userId]
            );
        }

        View::render('crm::today', [
            'pageTitle' => 'عملي اليوم',
            'spaces' => $spaces,
            'overdue' => $overdue,
            'today' => $today,
            'upcoming' => $upcoming,
            'untouched' => $untouched,
            'tasks' => $tasks,
        ]);
    }
}

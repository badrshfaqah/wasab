<?php

namespace Modules\Crm\Models;

use App\Core\Database;

/** أرقام المساحة للوحة والتقارير - كلها مقيّدة بالمساحة وبالفترة المختارة. */
class Stats
{
    /**
     * لوحة المساحة. $filters: from, to, owner (مستخدم), category, pipeline.
     */
    public static function workspace(int $workspaceId, array $filters = []): array
    {
        $from = $filters['from'] ?? date('Y-m-01');
        $to = $filters['to'] ?? date('Y-m-d');
        $ownerId = (int) ($filters['owner'] ?? 0);
        $categoryId = (int) ($filters['category'] ?? 0);

        // شروط مشتركة على علاقة الجهة
        $relWhere = 'r.workspace_id = :w';
        $relParams = ['w' => $workspaceId];
        if ($ownerId) {
            $relWhere .= ' AND r.owner_id = :owner';
            $relParams['owner'] = $ownerId;
        }
        if ($categoryId) {
            $relWhere .= ' AND EXISTS (SELECT 1 FROM crm_org_categories oc WHERE oc.workspace_org_id = r.id AND oc.category_id = :cat)';
            $relParams['cat'] = $categoryId;
        }

        $count = fn (string $extra, array $extraParams = []) => (int) (Database::first(
            "SELECT COUNT(*) AS c FROM crm_workspace_orgs r WHERE {$relWhere}{$extra}",
            $relParams + $extraParams
        )['c'] ?? 0);

        $today = date('Y-m-d 23:59:59');
        $orgs = [
            'total' => $count(''),
            'new' => $count(' AND r.created_at BETWEEN :from AND :to', ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59']),
            'contacted' => $count(' AND r.last_activity_at IS NOT NULL'),
            'untouched' => $count(' AND r.last_activity_at IS NULL'),
            'due_today' => $count(' AND r.next_action_at IS NOT NULL AND DATE(r.next_action_at) = :d', ['d' => date('Y-m-d')]),
            'overdue' => $count(' AND r.next_action_at IS NOT NULL AND r.next_action_at < :now', ['now' => date('Y-m-d 00:00:00')]),
            'stale' => $count(' AND (r.last_activity_at IS NULL OR r.last_activity_at < :stale)', ['stale' => date('Y-m-d H:i:s', strtotime('-30 days'))]),
        ];

        // الأنشطة خلال الفترة، مصنّفة بالنوع
        $activityWhere = 'a.workspace_id = :w AND a.occurred_at BETWEEN :from AND :to';
        $activityParams = ['w' => $workspaceId, 'from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];
        // من لا يملك «مشاهدة أنشطة الآخرين» تُحسب له أنشطته وحدها
        $restrictTo = (int) ($filters['activity_user'] ?? 0) ?: $ownerId;
        if ($restrictTo) {
            $activityWhere .= ' AND a.user_id = :owner';
            $activityParams['owner'] = $restrictTo;
        }
        $byType = Database::select(
            "SELECT a.type, COUNT(*) AS c FROM crm_activities a WHERE {$activityWhere} GROUP BY a.type ORDER BY c DESC",
            $activityParams
        );
        $activityTotal = array_sum(array_map(fn ($r) => (int) $r['c'], $byType));

        // الفرص
        $oppWhere = 'o.workspace_id = :w';
        $oppParams = ['w' => $workspaceId];
        if ($ownerId) {
            $oppWhere .= ' AND o.owner_id = :owner';
            $oppParams['owner'] = $ownerId;
        }
        if (!empty($filters['pipeline'])) {
            $oppWhere .= ' AND o.pipeline_id = :p';
            $oppParams['p'] = (int) $filters['pipeline'];
        }
        $opp = Database::first(
            "SELECT
                SUM(o.status = 'open') AS open_count,
                SUM(o.status = 'won') AS won_count,
                SUM(o.status = 'lost') AS lost_count,
                COALESCE(SUM(CASE WHEN o.status = 'open' THEN o.value END), 0) AS open_value,
                COALESCE(SUM(CASE WHEN o.status = 'won' THEN o.value END), 0) AS won_value
             FROM crm_opportunities o WHERE {$oppWhere}",
            $oppParams
        );

        // توزيع الفرص المفتوحة على المراحل
        $stages = Database::select(
            "SELECT s.name, s.color, COUNT(o.id) AS c
               FROM crm_stages s
               LEFT JOIN crm_opportunities o ON o.stage_id = s.id AND o.status = 'open'
               JOIN crm_pipelines p ON p.id = s.pipeline_id
              WHERE p.workspace_id = :w AND s.is_active = 1
              GROUP BY s.id ORDER BY s.sort_order",
            ['w' => $workspaceId]
        );

        // الأنشط في الفريق خلال الفترة - يُخفى عمّن لا يرى أنشطة غيره
        $topUsers = !empty($filters['activity_user']) ? [] : Database::select(
            "SELECT u.name, COUNT(*) AS c FROM crm_activities a
               JOIN users u ON u.id = a.user_id
              WHERE a.workspace_id = :w AND a.occurred_at BETWEEN :from AND :to
              GROUP BY a.user_id ORDER BY c DESC LIMIT 5",
            ['w' => $workspaceId, 'from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59']
        );

        return [
            'from' => $from,
            'to' => $to,
            'orgs' => $orgs,
            'activities' => ['total' => $activityTotal, 'by_type' => $byType],
            'opportunities' => [
                'open' => (int) ($opp['open_count'] ?? 0),
                'won' => (int) ($opp['won_count'] ?? 0),
                'lost' => (int) ($opp['lost_count'] ?? 0),
                'open_value' => (float) ($opp['open_value'] ?? 0),
                'won_value' => (float) ($opp['won_value'] ?? 0),
            ],
            'stages' => $stages,
            'top_users' => $topUsers,
        ];
    }

    /** أرقام مختصرة لكل مساحات الشركة (للتقرير الشهري). */
    public static function companySummary(int $companyId, string $from, string $to): array
    {
        $row = Database::first(
            "SELECT
                (SELECT COUNT(*) FROM crm_workspaces WHERE company_id = :c AND status = 'active') AS spaces,
                (SELECT COUNT(*) FROM crm_organizations WHERE company_id = :c2) AS orgs,
                (SELECT COUNT(*) FROM crm_activities a JOIN crm_workspaces w ON w.id = a.workspace_id
                  WHERE w.company_id = :c3 AND a.occurred_at BETWEEN :from AND :to) AS activities,
                (SELECT COUNT(*) FROM crm_opportunities o JOIN crm_workspaces w ON w.id = o.workspace_id
                  WHERE w.company_id = :c4 AND o.status = 'open') AS open_opps,
                (SELECT COUNT(*) FROM crm_opportunities o JOIN crm_workspaces w ON w.id = o.workspace_id
                  WHERE w.company_id = :c5 AND o.status = 'won' AND o.closed_at BETWEEN :from2 AND :to2) AS won_opps",
            [
                'c' => $companyId, 'c2' => $companyId, 'c3' => $companyId, 'c4' => $companyId, 'c5' => $companyId,
                'from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59',
                'from2' => $from . ' 00:00:00', 'to2' => $to . ' 23:59:59',
            ]
        );
        return array_map('intval', $row ?: []);
    }
}

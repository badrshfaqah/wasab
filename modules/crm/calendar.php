<?php

use App\Core\Database;
use App\Core\Permission;

/**
 * مزوّد أحداث التقويم الموحّد من CRM: متابعات المستخدم المعلّقة داخل المساحات
 * التي يصل إليها فقط.
 */
return function (array $user, string $fromDate, string $toDate): array {
    if (!Permission::check('crm.view') || empty($user['company_id'])) {
        return [];
    }
    $rows = Database::select(
        "SELECT a.id, a.next_action_at, a.next_action_note, o.name AS org_name, o.id AS org_id, a.workspace_id, w.icon
           FROM crm_activities a
           JOIN contacts_organizations o ON o.id = a.organization_id
           JOIN crm_workspaces w ON w.id = a.workspace_id
           JOIN crm_workspace_members m ON m.workspace_id = a.workspace_id AND m.user_id = :u
          WHERE w.company_id = :c
            AND a.next_action_status = 'pending'
            AND COALESCE(a.next_action_owner_id, a.user_id) = :u2
            AND DATE(a.next_action_at) BETWEEN :from AND :to
          ORDER BY a.next_action_at",
        ['u' => (int) $user['id'], 'u2' => (int) $user['id'], 'c' => (int) $user['company_id'], 'from' => $fromDate, 'to' => $toDate]
    );

    return array_map(fn ($r) => [
        'date' => date('Y-m-d', strtotime($r['next_action_at'])),
        'title' => '🔔 متابعة: ' . $r['org_name'] . ($r['next_action_note'] ? ' — ' . $r['next_action_note'] : ''),
        'url' => route('/crm/w/' . $r['workspace_id'] . '/orgs/' . $r['org_id']),
        'color' => '#7c3aed',
    ], $rows);
};

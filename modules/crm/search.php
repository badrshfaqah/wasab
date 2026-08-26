<?php

use App\Core\Database;
use App\Core\Permission;

/**
 * البحث الموحّد: جهات وأشخاص CRM - داخل المساحات التي يصل إليها المستخدم فقط،
 * فلا يكشف البحثُ ما تحجبه الصلاحيات.
 */
return function (array $user, string $query): array {
    if (!Permission::check('crm.view') || empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $isAdmin = $user['membership_type'] === 'system_admin'
        || $user['membership_type'] === 'company_admin'
        || Permission::check('crm.manage');

    // شرط الوصول: المدير يرى الكل، وغيره يرى مساحاته وحدها
    $access = $isAdmin ? '' : ' AND EXISTS (SELECT 1 FROM crm_workspace_members m WHERE m.workspace_id = r.workspace_id AND m.user_id = :u)';
    $params = ['c' => $companyId, 'q' => "%{$query}%", 'q2' => "%{$query}%", 'q3' => "%{$query}%"];
    if (!$isAdmin) {
        $params['u'] = $userId;
    }

    $orgs = Database::select(
        "SELECT DISTINCT o.id, o.name, o.city, o.sector, r.workspace_id, w.icon, w.name AS workspace_name
           FROM crm_organizations o
           JOIN crm_workspace_orgs r ON r.organization_id = o.id
           JOIN crm_workspaces w ON w.id = r.workspace_id
          WHERE o.company_id = :c AND (o.name LIKE :q OR o.email LIKE :q2 OR o.phone LIKE :q3){$access}
          ORDER BY o.name LIMIT 8",
        $params
    );

    $contactParams = ['c' => $companyId, 'q' => "%{$query}%", 'q2' => "%{$query}%", 'q3' => "%{$query}%"];
    if (!$isAdmin) {
        $contactParams['u'] = $userId;
    }
    $contacts = Database::select(
        "SELECT DISTINCT ct.id, ct.name, ct.job_title, o.name AS org_name, o.id AS org_id, r.workspace_id
           FROM crm_contacts ct
           JOIN crm_organizations o ON o.id = ct.organization_id
           JOIN crm_workspace_orgs r ON r.organization_id = o.id
          WHERE ct.company_id = :c AND (ct.name LIKE :q OR ct.email LIKE :q2 OR ct.mobile LIKE :q3){$access}
          ORDER BY ct.name LIMIT 5",
        $contactParams
    );

    $results = array_map(fn ($r) => [
        'title' => '🤝 ' . $r['name'],
        'subtitle' => trim(($r['sector'] ?? '') . ' · ' . ($r['city'] ?? '') . ' · ' . $r['workspace_name'], ' ·'),
        'url' => route('/crm/w/' . $r['workspace_id'] . '/orgs/' . $r['id']),
    ], $orgs);

    foreach ($contacts as $c) {
        $results[] = [
            'title' => '👤 ' . $c['name'],
            'subtitle' => trim(($c['job_title'] ?? '') . ' — ' . $c['org_name'], ' —'),
            'url' => route('/crm/w/' . $c['workspace_id'] . '/orgs/' . $c['org_id']),
        ];
    }

    return $results;
};

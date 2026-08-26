<?php

use App\Core\Database;
use App\Core\Permission;

/**
 * البحث الموحّد من CRM: الجهات داخل المساحات التي يصل إليها المستخدم فقط، فلا
 * يكشف البحثُ ما تحجبه الصلاحيات. الأشخاص لا يُبحث عنهم هنا لأنهم ملك دليل
 * «جهات الاتصال» ويظهرون من مزوّده - فلا يتكرر الاسم مرتين في نتيجة واحدة.
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
           FROM contacts_organizations o
           JOIN crm_workspace_orgs r ON r.organization_id = o.id
           JOIN crm_workspaces w ON w.id = r.workspace_id
          WHERE o.company_id = :c AND (o.name LIKE :q OR o.email LIKE :q2 OR o.phone LIKE :q3){$access}
          ORDER BY o.name LIMIT 8",
        $params
    );

    $results = array_map(fn ($r) => [
        'title' => '🤝 ' . $r['name'],
        'subtitle' => trim(($r['sector'] ?? '') . ' · ' . ($r['city'] ?? '') . ' · ' . $r['workspace_name'], ' ·'),
        'url' => route('/crm/w/' . $r['workspace_id'] . '/orgs/' . $r['id']),
    ], $orgs);

    return $results;
};

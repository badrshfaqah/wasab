<?php

use App\Core\Database;
use App\Core\Permission;

/** البحث بنصوص المتابعات: المدير بمتابعات الفريق كلها، والموظف بمتابعاته فقط. */
return function (array $user, string $query): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $canTeam = Permission::check('checkins.view_team');
    if (!$canTeam && !Permission::check('checkins.submit')) {
        return [];
    }

    $like = '%' . $query . '%';
    $scope = $canTeam ? 'e.company_id = :scope' : 'e.user_id = :scope';
    $rows = Database::select(
        "SELECT e.entry_date, u.name,
                CONCAT_WS(' | ', e.done_text, e.plan_text, e.blockers_text) AS full_text
           FROM checkins_entries e JOIN users u ON u.id = e.user_id
          WHERE {$scope}
            AND (e.done_text LIKE :q1 OR e.plan_text LIKE :q2 OR e.blockers_text LIKE :q3)
          ORDER BY e.entry_date DESC LIMIT 8",
        [
            'scope' => $canTeam ? (int) $user['company_id'] : (int) $user['id'],
            'q1' => $like, 'q2' => $like, 'q3' => $like,
        ]
    );

    return array_map(fn ($r) => [
        'title' => 'متابعة ' . $r['name'] . ' - ' . $r['entry_date'],
        'subtitle' => mb_substr((string) $r['full_text'], 0, 80),
        'icon' => '📝',
        'url' => $canTeam ? route('/checkins/team?date=' . $r['entry_date']) : route('/checkins'),
    ], $rows);
};

<?php

use App\Core\Database;
use App\Core\Permission;
use Modules\Checkins\Models\CheckinEntry;

return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];
    $today = date('Y-m-d');
    $widgets = [];

    if (Permission::check('checkins.submit')) {
        $mine = CheckinEntry::forUserOnDate((int) $user['id'], $today);
        $widgets[] = [
            'type' => 'stat',
            'label' => 'متابعتي اليوم',
            'value' => $mine ? '✓' : '✗',
            'icon' => '📝',
            'color' => $mine ? 'success' : 'warning',
            'url' => route('/checkins'),
        ];
    }

    if (Permission::check('checkins.view_team')) {
        $total = Database::count('users', 'company_id = :c AND status = "active"', ['c' => $companyId]);
        $submitted = CheckinEntry::submittedTodayCount($companyId, $today);
        $widgets[] = [
            'type' => 'stat',
            'label' => 'متابعات الفريق اليوم',
            'value' => "{$submitted}/{$total}",
            'icon' => '👥',
            'color' => $submitted >= $total ? 'success' : 'primary',
            'url' => route('/checkins/team'),
        ];

        $blockers = Database::select(
            "SELECT e.blockers_text, u.name
               FROM checkins_entries e JOIN users u ON u.id = e.user_id
              WHERE e.company_id = :c AND e.entry_date = :d
                AND e.blockers_text IS NOT NULL AND e.blockers_text != ''
              ORDER BY e.id DESC LIMIT 5",
            ['c' => $companyId, 'd' => $today]
        );
        $widgets[] = [
            'type' => 'list',
            'title' => 'معوقات اليوم',
            'icon' => '🚧',
            'empty_text' => 'لا معوقات مسجلة اليوم 👌',
            'items' => array_map(fn ($b) => [
                'label' => mb_substr($b['blockers_text'], 0, 60) . (mb_strlen($b['blockers_text']) > 60 ? '...' : ''),
                'url' => route('/checkins/team'),
                'meta' => $b['name'],
            ], $blockers),
        ];
    }

    return $widgets;
};

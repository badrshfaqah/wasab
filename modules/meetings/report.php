<?php

use App\Core\Database;

/** أرقام "الاجتماعات" لصفحة التقارير - على مستوى الشركة كاملة. */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];

    $upcoming = Database::count('meetings_meetings', 'company_id = :c AND status = "scheduled" AND starts_at >= NOW()', ['c' => $companyId]);
    $thisWeek = Database::count(
        'meetings_meetings',
        'company_id = :c AND status = "scheduled" AND starts_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)',
        ['c' => $companyId]
    );
    $completed = Database::count('meetings_meetings', 'company_id = :c AND status = "completed"', ['c' => $companyId]);

    return [
        ['label' => 'اجتماعات قادمة', 'value' => $upcoming, 'icon' => '🗓️', 'color' => 'primary', 'url' => route('/meetings')],
        ['label' => 'خلال ٧ أيام', 'value' => $thisWeek, 'icon' => '📆', 'color' => $thisWeek > 0 ? 'warning' : 'success'],
        ['label' => 'اجتماعات منتهية', 'value' => $completed, 'icon' => '✅', 'color' => 'success'],
    ];
};

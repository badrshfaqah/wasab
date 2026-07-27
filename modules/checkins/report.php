<?php

use App\Core\Database;
use Modules\Checkins\Controllers\CheckinController;
use Modules\Checkins\Models\CheckinEntry;

return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];
    $today = date('Y-m-d');

    $total = Database::count('users', 'company_id = :c AND status = "active"', ['c' => $companyId]);
    $submitted = CheckinEntry::submittedTodayCount($companyId, $today);
    $compliance = CheckinEntry::complianceLast7Days($companyId, CheckinController::workdays($companyId));

    return [
        ['label' => 'متابعات اليوم', 'value' => "{$submitted}/{$total}", 'icon' => '📝', 'color' => $submitted >= $total ? 'success' : 'primary', 'url' => route('/checkins/team')],
        ['label' => 'التزام آخر ٧ أيام', 'value' => $compliance['rate'] . '%', 'icon' => '📊', 'color' => $compliance['rate'] >= 80 ? 'success' : ($compliance['rate'] >= 50 ? 'warning' : 'danger'), 'url' => route('/checkins/team')],
    ];
};

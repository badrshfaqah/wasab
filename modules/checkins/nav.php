<?php
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $canSubmit = \App\Core\Permission::check('checkins.submit');
    $canViewTeam = \App\Core\Permission::check('checkins.view_team');
    if (!$canSubmit && !$canViewTeam) {
        return [];
    }

    // شارة تذكير: لم يسجل متابعة اليوم بعد (في أيام العمل فقط)
    $badge = null;
    if ($canSubmit) {
        $workdays = \Modules\Checkins\Controllers\CheckinController::workdays((int) $user['company_id']);
        if (in_array((int) date('w'), $workdays, true)
            && !\Modules\Checkins\Models\CheckinEntry::forUserOnDate((int) $user['id'], date('Y-m-d'))) {
            $badge = 1;
        }
    }

    return [
        [
            'label' => 'المتابعة اليومية',
            'icon' => '📝',
            'url' => route('/checkins'),
            'badge' => $badge,
        ],
    ];
};

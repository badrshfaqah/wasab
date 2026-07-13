<?php

use App\Core\Database;

/** أرقام "الملف الوظيفي" لصفحة التقارير - على مستوى الشركة كاملة. */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];

    $active = Database::count('employees_profiles', 'company_id = :c AND status = "active"', ['c' => $companyId]);
    $onLeave = Database::count('employees_profiles', 'company_id = :c AND status = "on_leave"', ['c' => $companyId]);
    $contractsEnding = Database::count(
        'employees_profiles',
        'company_id = :c AND status = "active" AND contract_end_date IS NOT NULL AND contract_end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)',
        ['c' => $companyId]
    );

    return [
        ['label' => 'موظفون نشطون', 'value' => $active, 'icon' => '🧑‍💼', 'color' => 'primary', 'url' => route('/employees')],
        ['label' => 'في إجازة', 'value' => $onLeave, 'icon' => '🌴', 'color' => $onLeave > 0 ? 'warning' : 'success'],
        ['label' => 'عقود قاربت الانتهاء (٣٠ يوم)', 'value' => $contractsEnding, 'icon' => '⏳', 'color' => $contractsEnding > 0 ? 'danger' : 'success'],
    ];
};

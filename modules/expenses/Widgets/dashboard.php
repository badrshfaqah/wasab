<?php

use App\Core\Database;
use App\Core\Permission;

/** عناصر الرئيسية لإضافة المصروفات. */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];
    $widgets = [];

    $canManage = Permission::check('expenses.manage')
        || $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin';
    if ($canManage) {
        $pending = Database::count('expenses_claims', "company_id = :c AND status = 'pending'", ['c' => $companyId]);
        if ($pending > 0) {
            $widgets[] = [
                'type' => 'stat',
                'label' => 'مصروفات بانتظار الاعتماد',
                'value' => $pending,
                'icon' => '💰',
                'color' => 'warning',
                'url' => route('/expenses'),
            ];
        }
    }
    return $widgets;
};

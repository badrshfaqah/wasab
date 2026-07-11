<?php

use App\Core\Permission;
use Modules\Employees\Models\Employee;

/**
 * عناصر الصفحة الرئيسية لإضافة الملف الوظيفي. يُستدعى فقط عندما تكون الإضافة مفعّلة.
 */
return function (array $user): array {
    $canView = Permission::check('employees.view') || Permission::check('employees.manage');
    if (!$canView || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $widgets = [];

    if (Permission::check('employees.create') || Permission::check('employees.manage')) {
        $widgets[] = ['type' => 'shortcut', 'label' => 'ملف وظيفي جديد', 'icon' => '➕', 'url' => route('/employees/create')];
    }

    $widgets[] = [
        'type' => 'stat',
        'label' => 'الموظفون النشطون',
        'value' => Employee::countByStatus($companyId, 'active'),
        'icon' => '🪪',
        'color' => 'success',
        'url' => route('/employees'),
    ];

    $onLeave = Employee::countByStatus($companyId, 'on_leave');
    $widgets[] = [
        'type' => 'stat',
        'label' => 'موظفون بإجازة حالياً',
        'value' => $onLeave,
        'icon' => '🌴',
        'color' => $onLeave > 0 ? 'warning' : 'success',
        'url' => route('/employees?status=on_leave'),
    ];

    return $widgets;
};

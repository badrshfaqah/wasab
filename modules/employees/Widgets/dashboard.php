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

    // يشمل من لديهم إجازة معتمدة تغطي اليوم + من حالتهم "بإجازة" يدوياً
    $onLeave = \Modules\Employees\Models\EmployeeLeave::onLeaveTodayCount($companyId);
    $widgets[] = [
        'type' => 'stat',
        'label' => 'موظفون بإجازة حالياً',
        'value' => $onLeave,
        'icon' => '🌴',
        'color' => $onLeave > 0 ? 'warning' : 'success',
        'url' => route('/employees/leaves'),
    ];

    // طلبات الإجازة المعلّقة - لمن يقرر فيها فقط
    $canDecide = Permission::check('employees.manage') || Permission::check('employees.edit')
        || $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin';
    if ($canDecide) {
        $pendingLeaves = \Modules\Employees\Models\EmployeeLeave::countPending($companyId);
        if ($pendingLeaves > 0) {
            $widgets[] = [
                'type' => 'stat',
                'label' => 'طلبات إجازة بانتظارك',
                'value' => $pendingLeaves,
                'icon' => '📨',
                'color' => 'warning',
                'url' => route('/employees/leaves'),
            ];
        }
    }

    // تنبيه الوثائق المنتهية/القريبة - بيانات حسّاسة فتُعرض لمن يملك صلاحية ذلك فقط
    if (Permission::check('employees.view_sensitive') || Permission::check('employees.manage')) {
        $expiring = Employee::countExpiringDocuments($companyId, 60);
        if ($expiring > 0) {
            $widgets[] = [
                'type' => 'stat',
                'label' => 'وثائق تنتهي قريباً',
                'value' => $expiring,
                'icon' => '🔔',
                'color' => 'danger',
                'url' => route('/employees/expiring'),
            ];
        }
    }

    return $widgets;
};

<?php

use App\Core\Permission;
use Modules\Employees\Models\Employee;
use Modules\Employees\Models\EmployeeCertification;
use Modules\Employees\Models\EmployeeLeave;

/**
 * مزوّد أحداث التقويم الموحّد: انتهاء عقود الموظفين، رخص القيادة، والدورات/الشهادات.
 * بيانات حساسة إدارياً، فتظهر فقط لمن يدير الإضافة أو يملك صلاحية المشاهدة العامة -
 * ليست مرئية للموظف العادي حتى في تقويمه الخاص، تفادياً لتسريب بيانات موظفين آخرين.
 */
return function (array $user, string $fromDate, string $toDate): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];
    $canView = Permission::check('employees.view') || Permission::check('employees.manage')
        || $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin';

    $events = [];

    // الإجازات المعتمدة: المدير يرى الجميع، والموظف العادي يرى إجازاته هو فقط
    foreach (EmployeeLeave::approvedInRange($companyId, $fromDate, $toDate) as $lv) {
        $isMine = !empty($lv['linked_user_id']) && (int) $lv['linked_user_id'] === (int) $user['id'];
        if (!$canView && !$isMine) {
            continue;
        }
        $events[] = [
            'date' => max($lv['start_date'], $fromDate),
            'title' => '🌴 إجازة: ' . $lv['full_name'] . ($lv['start_date'] !== $lv['end_date'] ? ' (حتى ' . $lv['end_date'] . ')' : ''),
            'url' => route('/employees/leaves'),
        ];
    }

    if (!$canView) {
        return $events;
    }

    foreach (Employee::forCalendarRange($companyId, $fromDate, $toDate) as $row) {
        if (!$row['due_date']) {
            continue;
        }
        $events[] = [
            'date' => $row['due_date'],
            'title' => '🪪 انتهاء ' . $row['kind'] . ': ' . $row['full_name'],
            'url' => route('/employees/' . $row['id']),
        ];
    }

    foreach (EmployeeCertification::forCalendarRange($companyId, $fromDate, $toDate) as $row) {
        if (!$row['due_date']) {
            continue;
        }
        $events[] = [
            'date' => $row['due_date'],
            'title' => '🎓 انتهاء صلاحية: ' . $row['title'] . ' (' . $row['full_name'] . ')',
            'url' => route('/employees/' . $row['employee_id']),
        ];
    }

    return $events;
};

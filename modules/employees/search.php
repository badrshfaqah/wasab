<?php

use App\Core\Permission;
use Modules\Employees\Models\Employee;

/**
 * مزوّد البحث الموحّد من إضافة الملف الوظيفي: يقتصر على من يملك صلاحية عرض قائمة
 * الموظفين (وليس استثناء "شاهد ملفك الخاص" الخاص بصفحة الملف نفسها - البحث ميزة إضافية
 * وليست ضرورية لذلك الاستثناء).
 */
return function (array $user, string $query): array {
    if (!(Permission::check('employees.view') || Permission::check('employees.manage')) || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $result = Employee::paginate($companyId, ['q' => $query], 1, 8);

    return array_map(fn ($e) => [
        'title' => $e['full_name'],
        'subtitle' => trim(($e['job_title'] ?? '') . ($e['department'] ? ' - ' . $e['department'] : '')) ?: status_label($e['status']),
        'icon' => '🧑‍💼',
        'url' => route('/employees/' . $e['id']),
    ], $result['rows']);
};

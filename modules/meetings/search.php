<?php

use App\Core\Permission;
use Modules\Meetings\Models\Meeting;

/**
 * مزوّد البحث الموحّد من إضافة الاجتماعات: يعيد استخدام Meeting::paginate() بنفس فلتر
 * البحث ونطاق الرؤية (seeAll) المعتمد أصلاً بالإضافة.
 */
return function (array $user, string $query): array {
    if (!Permission::check('meetings.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $seeAll = $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin' || Permission::check('meetings.manage');

    $result = Meeting::paginate($companyId, $userId, $seeAll, ['q' => $query], 1, 8);

    return array_map(fn ($m) => [
        'title' => $m['title'],
        'subtitle' => format_date($m['starts_at'], 'Y-m-d H:i'),
        'icon' => '🗓️',
        'url' => route('/meetings/' . $m['id']),
    ], $result['rows']);
};

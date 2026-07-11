<?php

use App\Core\Permission;
use Modules\Meetings\Models\Meeting;

/**
 * مزوّد أحداث التقويم الموحّد من إضافة الاجتماعات: موعد كل اجتماع أنشأه المستخدم
 * أو مدعو له (أو كل اجتماعات الشركة لمن يدير الإضافة).
 */
return function (array $user, string $fromDate, string $toDate): array {
    if (!Permission::check('meetings.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $canManage = $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin' || Permission::check('meetings.manage');

    $rows = Meeting::forCalendarRange($companyId, $userId, $canManage, $fromDate, $toDate);

    return array_map(fn ($m) => [
        'date' => date('Y-m-d', strtotime($m['starts_at'])),
        'time' => date('H:i', strtotime($m['starts_at'])),
        'title' => '🗓️ ' . $m['title'],
        'url' => route('/meetings/' . $m['id']),
    ], $rows);
};

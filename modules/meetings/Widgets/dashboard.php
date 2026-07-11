<?php

use App\Core\Permission;
use Modules\Meetings\Models\Meeting;

/**
 * عناصر الصفحة الرئيسية لإضافة الاجتماعات. يُستدعى فقط عندما تكون الإضافة مفعّلة.
 */
return function (array $user): array {
    if (!Permission::check('meetings.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $widgets = [];

    if (Permission::check('meetings.create')) {
        $widgets[] = ['type' => 'shortcut', 'label' => 'اجتماع جديد', 'icon' => '➕', 'url' => route('/meetings/create')];
    }

    $pendingCount = Meeting::countPendingResponse($companyId, $userId);
    $widgets[] = [
        'type' => 'stat',
        'label' => 'دعوات اجتماعات بانتظار ردك',
        'value' => $pendingCount,
        'icon' => '📩',
        'color' => $pendingCount > 0 ? 'warning' : 'success',
        'url' => route('/meetings'),
    ];

    $widgets[] = [
        'type' => 'list', 'title' => 'اجتماعاتي القادمة', 'icon' => '🗓️', 'empty_text' => 'لا توجد اجتماعات قادمة',
        'items' => array_map(fn ($m) => [
            'label' => $m['title'],
            'url' => route('/meetings/' . $m['id']),
            'meta' => format_date($m['starts_at'], 'Y-m-d H:i'),
        ], Meeting::upcomingForUser($companyId, $userId, 5)),
    ];

    return $widgets;
};

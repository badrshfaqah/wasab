<?php

use App\Core\Permission;
use Modules\Inbox\Models\InboxMessage;

/**
 * عناصر الصفحة الرئيسية لمركز المراسلات. يُستدعى فقط عندما تكون الإضافة مفعّلة،
 * ويُفلتر تلقائياً حسب صلاحية المستخدم الحالي.
 */
return function (array $user): array {
    if (!Permission::check('inbox.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $widgets = [];

    $unread = InboxMessage::unreadCount($companyId);
    $widgets[] = [
        'type' => 'stat',
        'label' => 'رسائل غير مقروءة',
        'value' => $unread,
        'icon' => '📨',
        'color' => $unread > 0 ? 'warning' : 'success',
        'url' => route('/inbox?scope=unread'),
    ];

    $recent = InboxMessage::recent($companyId, 5);
    $widgets[] = [
        'type' => 'list',
        'title' => 'آخر الرسائل الواردة',
        'icon' => '📨',
        'empty_text' => 'لا توجد رسائل واردة بعد',
        'items' => array_map(fn ($m) => [
            'label' => ($m['is_read'] ? '' : '● ') . ($m['subject'] ?: ('رسالة من ' . ($m['sender_name'] ?: 'مجهول'))),
            'url' => route('/inbox/' . $m['id']),
            'meta' => $m['site_name'] ?? '',
        ], $recent),
    ];

    return $widgets;
};

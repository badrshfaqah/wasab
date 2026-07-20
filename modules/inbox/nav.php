<?php
/**
 * رابط القائمة الجانبية لمركز المراسلات، مع عدّاد الرسائل غير المقروءة.
 * يُستدعى فقط عندما تكون الإضافة مفعّلة.
 */
return function (array $user): array {
    if (!\App\Core\Permission::check('inbox.view') || empty($user['company_id'])) {
        return [];
    }

    $unread = \Modules\Inbox\Models\InboxMessage::unreadCount((int) $user['company_id']);

    return [
        [
            'label' => 'مركز المراسلات',
            'icon' => '📨',
            'url' => route('/inbox'),
            'badge' => $unread > 0 ? $unread : null,
        ],
    ];
};

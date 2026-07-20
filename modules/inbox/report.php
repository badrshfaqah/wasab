<?php

use App\Core\Database;

/**
 * أرقام "مركز المراسلات" لصفحة التقارير - على مستوى الشركة كاملة (لمدير الشركة).
 */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];

    $total = Database::count('inbox_messages', 'company_id = :c', ['c' => $companyId]);
    $unread = Database::count('inbox_messages', 'company_id = :c AND is_read = 0', ['c' => $companyId]);
    $today = Database::count('inbox_messages', 'company_id = :c AND received_at >= CURDATE()', ['c' => $companyId]);

    return [
        ['label' => 'إجمالي الرسائل', 'value' => $total, 'icon' => '📨', 'color' => 'primary', 'url' => route('/inbox?scope=all')],
        ['label' => 'رسائل غير مقروءة', 'value' => $unread, 'icon' => '✉️', 'color' => $unread > 0 ? 'warning' : 'success', 'url' => route('/inbox?scope=unread')],
        ['label' => 'رسائل اليوم', 'value' => $today, 'icon' => '📬', 'color' => 'info', 'url' => route('/inbox?scope=all')],
    ];
};

<?php

use App\Core\Database;
use App\Core\Permission;

/**
 * مزوّد البحث الموحّد من مركز المراسلات: يبحث باسم المرسل والإيميل والجوال
 * والموضوع ونص الرسالة.
 */
return function (array $user, string $query): array {
    if (!Permission::check('inbox.view') || empty($user['company_id'])) {
        return [];
    }

    $like = '%' . $query . '%';
    $rows = Database::select(
        'SELECT m.id, m.sender_name, m.subject, m.is_read, s.name AS site_name
           FROM inbox_messages m
           LEFT JOIN inbox_sites s ON s.id = m.site_id
          WHERE m.company_id = :c
            AND (m.sender_name LIKE :q1 OR m.sender_email LIKE :q2 OR m.sender_phone LIKE :q3 OR m.subject LIKE :q4 OR m.body LIKE :q5)
          ORDER BY m.id DESC LIMIT 8',
        ['c' => (int) $user['company_id'], 'q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like]
    );

    return array_map(fn ($m) => [
        'title' => $m['subject'] ?: ('رسالة من ' . ($m['sender_name'] ?: 'مجهول')),
        'subtitle' => ($m['site_name'] ?? '-') . ($m['is_read'] ? '' : ' · غير مقروءة'),
        'icon' => '📨',
        'url' => route('/inbox/' . $m['id']),
    ], $rows);
};

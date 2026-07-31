<?php

use App\Core\Database;
use App\Core\Permission;

/** بحث موحّد: بعنوان الخطاب، رقمه، أو اسم المستفيد. */
return function (array $user, string $query): array {
    if (!Permission::check('forms.view') || empty($user['company_id'])) {
        return [];
    }
    $like = '%' . $query . '%';
    $rows = Database::select(
        "SELECT id, title, number, recipient_name FROM forms_letters
          WHERE company_id = :c AND (title LIKE :q1 OR number LIKE :q2 OR recipient_name LIKE :q3)
          ORDER BY id DESC LIMIT 8",
        ['c' => (int) $user['company_id'], 'q1' => $like, 'q2' => $like, 'q3' => $like]
    );
    return array_map(fn ($l) => [
        'title' => $l['title'] . ($l['recipient_name'] ? ' - ' . $l['recipient_name'] : ''),
        'subtitle' => 'خطاب رقم ' . $l['number'],
        'icon' => '📝',
        'url' => route('/forms/' . $l['id']),
    ], $rows);
};

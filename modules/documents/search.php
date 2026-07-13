<?php

use App\Core\Database;
use App\Core\Permission;

/**
 * مزوّد البحث الموحّد من إضافة المستندات: يبحث بالعنوان أو رقم المستند، بنفس نطاق
 * الرؤية المعتمد بالإضافة (مدير الشركة/النظام يرى الكل، غيره يرى مستنداته فقط).
 */
return function (array $user, string $query): array {
    if (!Permission::check('documents.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $canManage = $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin' || Permission::check('documents.manage');

    $where = 'company_id = :c AND (title LIKE :q1 OR number LIKE :q2)';
    $params = ['c' => $companyId, 'q1' => '%' . $query . '%', 'q2' => '%' . $query . '%'];
    if (!$canManage) {
        $where .= ' AND created_by = :u';
        $params['u'] = (int) $user['id'];
    }

    $rows = Database::select("SELECT id, title, number, status FROM documents_documents WHERE {$where} ORDER BY id DESC LIMIT 8", $params);

    return array_map(fn ($d) => [
        'title' => $d['title'] . ($d['number'] ? ' (' . $d['number'] . ')' : ''),
        'subtitle' => status_label($d['status']),
        'icon' => '📄',
        'url' => route('/documents/' . $d['id']),
    ], $rows);
};

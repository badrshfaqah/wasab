<?php

use App\Core\Database;

/** أرقام "المستندات" لصفحة التقارير - على مستوى الشركة كاملة. */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];

    $total = Database::count('documents_documents', 'company_id = :c', ['c' => $companyId]);
    $shared = (int) (Database::first(
        'SELECT COUNT(DISTINCT s.document_id) AS c FROM documents_shares s JOIN documents_documents d ON d.id = s.document_id WHERE d.company_id = :c',
        ['c' => $companyId]
    )['c'] ?? 0);
    $signed = Database::count('documents_documents', 'company_id = :c AND status = "signed"', ['c' => $companyId]);

    return [
        ['label' => 'إجمالي المستندات', 'value' => $total, 'icon' => '📄', 'color' => 'primary', 'url' => route('/documents?scope=company')],
        ['label' => 'مستندات مشتركة', 'value' => $shared, 'icon' => '🤝', 'color' => 'info'],
        ['label' => 'صادرة رسمياً (موقّعة)', 'value' => $signed, 'icon' => '✍️', 'color' => 'success'],
    ];
};

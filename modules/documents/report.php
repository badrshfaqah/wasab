<?php

use App\Core\Database;

/** أرقام "المستندات" لصفحة التقارير - على مستوى الشركة كاملة. */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];

    $total = Database::count('documents_documents', 'company_id = :c', ['c' => $companyId]);
    $pendingApproval = Database::count('documents_documents', 'company_id = :c AND status = "pending_approval"', ['c' => $companyId]);
    $signed = Database::count('documents_documents', 'company_id = :c AND status = "signed"', ['c' => $companyId]);

    return [
        ['label' => 'إجمالي المستندات', 'value' => $total, 'icon' => '📄', 'color' => 'primary', 'url' => route('/documents')],
        ['label' => 'بانتظار الموافقة', 'value' => $pendingApproval, 'icon' => '⏳', 'color' => $pendingApproval > 0 ? 'warning' : 'success'],
        ['label' => 'موقّعة', 'value' => $signed, 'icon' => '✍️', 'color' => 'success'],
    ];
};

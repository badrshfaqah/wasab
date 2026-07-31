<?php

use App\Core\Database;

return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];
    $total = Database::count('forms_letters', 'company_id = :c', ['c' => $companyId]);
    $month = Database::count('forms_letters', 'company_id = :c AND created_at >= :d', ['c' => $companyId, 'd' => date('Y-m-01')]);
    return [
        ['label' => 'خطابات مولّدة', 'value' => $total, 'icon' => '📝', 'color' => 'primary', 'url' => route('/forms')],
        ['label' => 'خطابات هذا الشهر', 'value' => $month, 'icon' => '🗓️', 'color' => 'info', 'url' => route('/forms')],
    ];
};

<?php

use App\Core\Database;

/** أرقام "الهاتف" لصفحة التقارير - على مستوى الشركة كاملة. */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];

    $contacts = Database::count('phone_contacts', 'company_id = :c', ['c' => $companyId]);
    $activeExtensions = Database::first(
        'SELECT COUNT(*) AS c FROM phone_users pu JOIN users u ON u.id = pu.user_id WHERE u.company_id = :c AND pu.enabled = 1',
        ['c' => $companyId]
    )['c'] ?? 0;

    return [
        ['label' => 'جهات الاتصال', 'value' => $contacts, 'icon' => '📇', 'color' => 'primary', 'url' => route('/phone/contacts')],
        ['label' => 'تحويلات مفعّلة', 'value' => (int) $activeExtensions, 'icon' => '☎️', 'color' => 'success'],
    ];
};

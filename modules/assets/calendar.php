<?php

use App\Core\Permission;
use Modules\Assets\Models\Asset;

/** أحداث التقويم: تواريخ انتهاء ضمان الأصول (كتنبيه انتهاء الشهادات). */
return function (array $user, string $fromDate, string $toDate): array {
    if (!Permission::check('assets.view') || empty($user['company_id'])) {
        return [];
    }

    $rows = Asset::warrantyEvents((int) $user['company_id'], $fromDate, $toDate);
    return array_map(fn ($a) => [
        'date' => $a['warranty_expiry'],
        'title' => '🛡️ انتهاء ضمان: ' . $a['name'],
        'url' => route('/custody/' . $a['id']),
        'module' => 'assets',
    ], $rows);
};

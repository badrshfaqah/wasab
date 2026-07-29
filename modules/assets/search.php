<?php

use App\Core\Database;
use App\Core\Permission;

/** بحث موحّد: بالاسم، الرمز، الرقم التسلسلي، أو اسم الحامل الحالي. */
return function (array $user, string $query): array {
    if (!Permission::check('assets.view') || empty($user['company_id'])) {
        return [];
    }

    $like = '%' . $query . '%';
    $rows = Database::select(
        "SELECT id, name, asset_code, status, current_holder_name
           FROM assets_assets
          WHERE company_id = :c
            AND (name LIKE :q1 OR asset_code LIKE :q2 OR serial_number LIKE :q3 OR current_holder_name LIKE :q4)
          ORDER BY id DESC LIMIT 8",
        ['c' => (int) $user['company_id'], 'q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]
    );

    $labels = \Modules\Assets\Models\Asset::statusLabels();
    return array_map(fn ($a) => [
        'title' => $a['name'] . ($a['asset_code'] ? ' (' . $a['asset_code'] . ')' : ''),
        'subtitle' => ($labels[$a['status']] ?? $a['status']) . ($a['current_holder_name'] ? ' · بعهدة: ' . $a['current_holder_name'] : ''),
        'icon' => '📦',
        'url' => route('/assets/' . $a['id']),
    ], $rows);
};

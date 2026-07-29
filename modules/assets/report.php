<?php

use Modules\Assets\Models\Asset;

/** أرقام العهد والأصول لصفحة التقارير على مستوى الشركة. */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];

    $available = Asset::countByStatus($companyId, 'available');
    $assigned = Asset::countByStatus($companyId, 'assigned');
    $maintenance = Asset::countByStatus($companyId, 'maintenance');
    $total = $available + $assigned + $maintenance
        + Asset::countByStatus($companyId, 'retired')
        + Asset::countByStatus($companyId, 'lost');

    return [
        ['label' => 'إجمالي الأصول', 'value' => $total, 'icon' => '📦', 'color' => 'primary', 'url' => route('/assets')],
        ['label' => 'أصول بعهدة', 'value' => $assigned, 'icon' => '🤝', 'color' => 'info', 'url' => route('/assets?status=assigned')],
        ['label' => 'أصول متاحة', 'value' => $available, 'icon' => '✅', 'color' => 'success', 'url' => route('/assets?status=available')],
        ['label' => 'قيد الصيانة', 'value' => $maintenance, 'icon' => '🔧', 'color' => $maintenance > 0 ? 'warning' : 'muted', 'url' => route('/assets?status=maintenance')],
    ];
};

<?php

use App\Core\Permission;
use Modules\Crm\Models\Stats;

/** أرقام CRM في التقرير الشهري الموحّد - على مستوى الشركة. */
return function (array $user): array {
    if (empty($user['company_id']) || !Permission::check('crm.view')) {
        return [];
    }
    $from = date('Y-m-01');
    $to = date('Y-m-d');
    $s = Stats::companySummary((int) $user['company_id'], $from, $to);
    if (empty($s['orgs'])) {
        return [];
    }

    return [
        ['label' => 'جهات في الدليل', 'value' => $s['orgs'] ?? 0, 'icon' => '🤝', 'color' => 'primary', 'url' => route('/crm')],
        ['label' => 'أنشطة الشهر', 'value' => $s['activities'] ?? 0, 'icon' => '📞', 'color' => 'info'],
        ['label' => 'فرص مفتوحة', 'value' => $s['open_opps'] ?? 0, 'icon' => '💼', 'color' => 'warning'],
        ['label' => 'صفقات تمت هذا الشهر', 'value' => $s['won_opps'] ?? 0, 'icon' => '✅', 'color' => 'success'],
    ];
};

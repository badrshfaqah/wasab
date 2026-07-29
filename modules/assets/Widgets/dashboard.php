<?php

use App\Core\Auth;
use App\Core\Permission;
use Modules\Assets\Models\Asset;

/** عناصر الصفحة الرئيسية للعهد والأصول. */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];
    $widgets = [];

    // بطاقة إحصائية لمن يدير الأصول
    if (Permission::check('assets.view')) {
        $assigned = Asset::countByStatus($companyId, 'assigned');
        $widgets[] = [
            'type' => 'stat',
            'label' => 'أصول بعهدة',
            'value' => $assigned,
            'icon' => '📦',
            'color' => 'info',
            'url' => route('/assets?status=assigned'),
        ];
    }

    // قائمة "عهدي" لأي مستخدم مربوط بحساب (يرى ما بعهدته)
    if (Permission::check('assets.view') || Permission::check('assets.view_own')) {
        $mine = Asset::currentlyHeldByUser($companyId, Auth::id());
        if ($mine) {
            $widgets[] = [
                'type' => 'list',
                'title' => 'عهدي',
                'icon' => '📦',
                'empty_text' => 'لا عهد مسندة إليك',
                'items' => array_map(fn ($a) => [
                    'label' => $a['name'] . ($a['asset_code'] ? ' (' . $a['asset_code'] . ')' : ''),
                    'url' => route('/assets/' . $a['id']),
                    'meta' => $a['category_name'] ?? '',
                ], array_slice($mine, 0, 5)),
            ];
        }
    }

    return $widgets;
};

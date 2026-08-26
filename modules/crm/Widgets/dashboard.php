<?php

use App\Core\Permission;
use Modules\Crm\Models\Activity;
use Modules\Crm\Models\Workspace;

/**
 * بطاقات الصفحة الرئيسية من CRM: ما ينتظر المستخدم اليوم من متابعات - عبر
 * مساحاته وحدها.
 */
return function (array $user): array {
    if (!Permission::check('crm.view') || empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $spaces = Workspace::forUser($companyId, $userId);
    if (!$spaces) {
        return [];
    }
    $ids = array_map(fn ($w) => (int) $w['id'], $spaces);

    $due = Activity::dueFollowUps($companyId, $userId, $ids, date('Y-m-d'));
    $overdue = array_filter($due, fn ($r) => date('Y-m-d', strtotime($r['next_action_at'])) < date('Y-m-d'));

    $widgets = [];
    if ($due) {
        $widgets[] = [
            'type' => 'stat',
            'label' => 'متابعات CRM اليوم',
            'value' => count($due),
            'icon' => '🔔',
            'color' => $overdue ? 'danger' : 'info',
            'url' => route('/crm/today'),
        ];
    }
    $widgets[] = [
        'type' => 'list',
        'title' => 'مساحات CRM',
        'icon' => '🤝',
        'empty_text' => 'لا مساحات متاحة لك',
        'items' => array_map(fn ($w) => [
            'label' => $w['icon'] . ' ' . $w['name'],
            'url' => route('/crm/w/' . $w['id']),
            'meta' => (int) $w['orgs_count'] . ' جهة',
        ], array_slice($spaces, 0, 5)),
    ];
    return $widgets;
};

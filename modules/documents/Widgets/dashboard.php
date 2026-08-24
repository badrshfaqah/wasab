<?php

use App\Core\Auth;
use App\Core\Permission;
use Modules\Documents\Models\Document;

/**
 * عناصر الصفحة الرئيسية لإضافة المستندات. يُستدعى فقط عندما تكون الإضافة مفعّلة،
 * ويُفلتر تلقائياً حسب صلاحية المستخدم الحالي.
 */
return function (array $user): array {
    if (!Permission::check('documents.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $canManage = $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin' || Permission::check('documents.manage');

    $widgets = [];

    if (Permission::check('documents.create')) {
        $widgets[] = [
            'type' => 'shortcut',
            'label' => 'مستند جديد',
            'icon' => '➕',
            'url' => route('/documents/create'),
        ];
    }

    // الفلسفة التعاونية: بدل عدّاد الاعتمادات، عدّاد المستندات المشارَكة معي
    $sharedCount = Document::countSharedWith($companyId, $userId);
    if ($sharedCount > 0) {
        $widgets[] = [
            'type' => 'stat',
            'label' => 'مستندات مشتركة معي',
            'value' => $sharedCount,
            'icon' => '🤝',
            'color' => 'info',
            'url' => route('/documents?scope=shared'),
        ];
    }

    $recent = Document::recentFor($companyId, $canManage, $userId, 5);
    $widgets[] = [
        'type' => 'list',
        'title' => 'آخر المستندات',
        'icon' => '📄',
        'empty_text' => 'لا توجد مستندات بعد',
        'items' => array_map(fn ($d) => [
            'label' => $d['title'],
            'url' => route('/documents/' . $d['id']),
            'meta' => $d['number'] ?: '—',
        ], $recent),
    ];

    return $widgets;
};

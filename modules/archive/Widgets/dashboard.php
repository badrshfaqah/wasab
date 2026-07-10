<?php

use App\Core\Auth;
use App\Core\Permission;
use Modules\Archive\Models\ArchiveFile;
use Modules\Archive\Models\ArchiveSetting;

/**
 * عناصر الصفحة الرئيسية لإضافة أرشيف الملفات. يُستدعى فقط عندما تكون الإضافة مفعّلة،
 * وكل قائمة هنا مفلترة فعلياً حسب صلاحية مشاهدة كل ملف على حدة (وليس فقط صلاحية
 * الإضافة العامة)، حتى لا تُسرّب عناوين ملفات مقيّدة الوصول ضمن الودجت.
 */
return function (array $user): array {
    if ((!Permission::check('archive.view') && !Permission::check('archive.manage')) || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $isSystemAdmin = $user['membership_type'] === 'system_admin';
    $canManage = $isSystemAdmin || $user['membership_type'] === 'company_admin' || Permission::check('archive.manage');

    ArchiveFile::markExpiredPastDue($companyId);
    $widgets = [];

    if (Permission::check('archive.create') || $canManage) {
        $widgets[] = ['type' => 'shortcut', 'label' => 'رفع ملف جديد', 'icon' => '➕', 'url' => route('/archive/upload')];
    }

    $warningDays = (int) ArchiveSetting::getOrCreate($companyId)['expiry_warning_days'];
    $expiringCount = ArchiveFile::countExpiringSoon($companyId, $warningDays);
    $widgets[] = [
        'type' => 'stat',
        'label' => 'ملفات قاربت على الانتهاء',
        'value' => $expiringCount,
        'icon' => '⏰',
        'color' => $expiringCount > 0 ? 'warning' : 'success',
        'url' => route('/archive'),
    ];

    $toItems = fn (array $rows, string $metaField = 'category_name') => array_map(fn ($f) => [
        'label' => $f['title'] ?: $f['original_name'],
        'url' => route('/archive/' . $f['id']),
        'meta' => $f[$metaField] ?? '',
    ], $rows);

    $widgets[] = [
        'type' => 'list', 'title' => 'آخر الملفات المضافة', 'icon' => '🆕', 'empty_text' => 'لا توجد ملفات بعد',
        'items' => $toItems(ArchiveFile::recent($companyId, $userId, $isSystemAdmin, $canManage, 5)),
    ];

    $widgets[] = [
        'type' => 'list', 'title' => 'الملفات التي قاربت على الانتهاء', 'icon' => '⏰', 'empty_text' => 'لا توجد ملفات قاربت على الانتهاء',
        'items' => $toItems(ArchiveFile::expiringSoon($companyId, $userId, $isSystemAdmin, $canManage, $warningDays, 5), 'expires_at'),
    ];

    $widgets[] = [
        'type' => 'list', 'title' => 'أكثر الملفات مشاهدة', 'icon' => '👁️', 'empty_text' => 'لا توجد مشاهدات بعد',
        'items' => $toItems(ArchiveFile::mostViewed($companyId, $userId, $isSystemAdmin, $canManage, 5)),
    ];

    $widgets[] = [
        'type' => 'list', 'title' => 'الملفات التي رفعتها', 'icon' => '📤', 'empty_text' => 'لم ترفع أي ملف بعد',
        'items' => $toItems(ArchiveFile::myUploads($companyId, $userId, 5)),
    ];

    $widgets[] = [
        'type' => 'list', 'title' => 'الملفات المشتركة معي', 'icon' => '🔗', 'empty_text' => 'لا توجد ملفات مشتركة معك',
        'items' => $toItems(ArchiveFile::sharedWithMe($companyId, $userId, 5)),
    ];

    return $widgets;
};

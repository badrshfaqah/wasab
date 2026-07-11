<?php

use App\Core\Permission;
use Modules\Archive\Models\ArchiveFile;

/**
 * مزوّد أحداث التقويم الموحّد من إضافة أرشيف الملفات: تاريخ انتهاء صلاحية كل ملف
 * يملك المستخدم صلاحية مشاهدته فعلياً (نفس منطق isVisibleTo تماماً).
 */
return function (array $user, string $fromDate, string $toDate): array {
    if ((!Permission::check('archive.view') && !Permission::check('archive.manage')) || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $isSystemAdmin = $user['membership_type'] === 'system_admin';
    $isCompanyAdmin = $user['membership_type'] === 'company_admin';
    $canManage = $isSystemAdmin || $isCompanyAdmin || Permission::check('archive.manage');

    $rows = ArchiveFile::forCalendarRange($companyId, $userId, $isSystemAdmin, $canManage, $isCompanyAdmin, $fromDate, $toDate);

    return array_map(fn ($f) => [
        'date' => $f['expires_at'],
        'title' => '⏰ انتهاء: ' . ($f['title'] ?: $f['original_name']),
        'url' => route('/archive/' . $f['id']),
    ], $rows);
};

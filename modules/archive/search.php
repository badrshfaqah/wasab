<?php

use App\Core\Permission;
use Modules\Archive\Models\ArchiveFile;

/**
 * مزوّد البحث الموحّد من إضافة الأرشيف: يعيد استخدام ArchiveFile::paginate() بنفس فلتر
 * البحث ونطاق الرؤية (visibilitySql) المعتمد أصلاً بالإضافة، بدل تكرار منطق الصلاحيات.
 */
return function (array $user, string $query): array {
    if (!(Permission::check('archive.view') || Permission::check('archive.manage')) || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $isSystemAdmin = $user['membership_type'] === 'system_admin';
    $isCompanyAdmin = $user['membership_type'] === 'company_admin';
    $canManage = $isSystemAdmin || $isCompanyAdmin || Permission::check('archive.manage');

    $result = ArchiveFile::paginate($companyId, $userId, $isSystemAdmin, $canManage, $isCompanyAdmin, ['q' => $query], 1, 8);

    return array_map(fn ($f) => [
        'title' => $f['title'] ?: $f['original_name'],
        'subtitle' => $f['category_name'] ?? null,
        'icon' => '🗄️',
        'url' => route('/archive/' . $f['id']),
    ], $result['rows']);
};

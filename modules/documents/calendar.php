<?php

use App\Core\Permission;
use Modules\Documents\Models\Document;

/**
 * مزوّد أحداث التقويم الموحّد من إضافة المستندات: تاريخ المتابعة الاختياري لكل
 * مستند، بنفس نطاق الرؤية المعتاد (المُنشئ يرى مستنداته فقط، والمدير يرى الكل).
 */
return function (array $user, string $fromDate, string $toDate): array {
    if (!Permission::check('documents.view') || empty($user['company_id'])) {
        return [];
    }

    $companyId = (int) $user['company_id'];
    $userId = (int) $user['id'];
    $seeAll = $user['membership_type'] === 'system_admin' || $user['membership_type'] === 'company_admin' || Permission::check('documents.manage');

    $rows = Document::forCalendarRange($companyId, $seeAll, $userId, $fromDate, $toDate);

    return array_map(fn ($d) => [
        'date' => $d['follow_up_date'],
        'title' => '📄 متابعة: ' . $d['title'],
        'url' => route('/documents/' . $d['id']),
    ], $rows);
};

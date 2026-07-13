<?php

use App\Core\Database;

/** أرقام "الأرشيف" لصفحة التقارير - على مستوى الشركة كاملة (بلا اعتبار لفلترة الرؤية الفردية). */
return function (array $user): array {
    if (empty($user['company_id'])) {
        return [];
    }
    $companyId = (int) $user['company_id'];

    $total = Database::count('archive_files', 'company_id = :c AND deleted_at IS NULL', ['c' => $companyId]);
    $expiringSoon = Database::count(
        'archive_files',
        'company_id = :c AND deleted_at IS NULL AND status = "active" AND expires_at IS NOT NULL AND expires_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)',
        ['c' => $companyId]
    );
    $trashed = Database::count('archive_files', 'company_id = :c AND deleted_at IS NOT NULL', ['c' => $companyId]);

    return [
        ['label' => 'ملفات الأرشيف', 'value' => $total, 'icon' => '🗄️', 'color' => 'primary', 'url' => route('/archive')],
        ['label' => 'قاربت على الانتهاء (٣٠ يوم)', 'value' => $expiringSoon, 'icon' => '⏳', 'color' => $expiringSoon > 0 ? 'warning' : 'success'],
        ['label' => 'في سلة المحذوفات', 'value' => $trashed, 'icon' => '🗑️', 'color' => 'muted'],
    ];
};

<?php

use App\Core\Database;
use Modules\Archive\Models\ArchiveFile;

/**
 * صيانة دورية للأرشيف: (1) وسم الملفات المنتهية فعلياً تلقائياً، (2) تطبيق سياسة
 * الاحتفاظ (retention) لكل شركة فعّلتها. يُستدعى من ModuleManager::runCron() عبر
 * cron.php بجذر المشروع. عملية النظام تُسجَّل في سجل التدقيق مع معرّف الشركة.
 */
return function (): void {
    $companies = Database::select('SELECT id FROM companies');
    foreach ($companies as $c) {
        $companyId = (int) $c['id'];

        // (1) وسم المنتهية (كان يعتمد على زيارة الصفحة فقط)
        ArchiveFile::markExpiredPastDue($companyId);

        // (2) سياسة الاحتفاظ إن كانت مفعّلة لهذه الشركة
        $settings = Database::first(
            'SELECT retention_months, retention_action FROM archive_settings WHERE company_id = :c',
            ['c' => $companyId]
        );
        if (!$settings) {
            continue;
        }
        $months = (int) ($settings['retention_months'] ?? 0);
        $action = (string) ($settings['retention_action'] ?? 'none');
        if ($months < 1 || $action === 'none') {
            continue;
        }

        $count = ArchiveFile::applyRetention($companyId, $months, $action);
        if ($count > 0) {
            $label = $action === 'trash' ? 'نُقلت لسلة المحذوفات' : 'أُرشِفت';
            Database::insert('activity_log', [
                'user_id' => null,
                'company_id' => $companyId,
                'action' => 'archive.retention',
                'subject_type' => 'archive',
                'subject_id' => '',
                'description' => "سياسة الاحتفاظ: {$count} ملف {$label} (أقدم من {$months} شهر).",
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
};

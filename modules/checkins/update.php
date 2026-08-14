<?php
return function (PDO $pdo, string $fromVersion): void {
    if (version_compare($fromVersion, '1.1.0', '<')) {
        // مؤشر المعنويات: عمود اختياري لكل سجل متابعة يومية
        $exists = $pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'checkins_entries' AND column_name = 'mood'"
        )->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `checkins_entries` ADD COLUMN `mood` TINYINT UNSIGNED NULL COMMENT 'معنويات اليوم: 1 (مرهق) .. 5 (ممتاز) - اختياري' AFTER `user_id`");
        }
    }

    if (version_compare($fromVersion, '1.2.0', '<')) {
        // سجل الحضور والانصراف (سجل واحد لكل مستخدم/يوم)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `checkins_attendance` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `work_date` DATE NOT NULL,
                `in_at` DATETIME NOT NULL,
                `out_at` DATETIME NULL,
                UNIQUE KEY `checkins_attendance_unique` (`company_id`, `user_id`, `work_date`),
                KEY `checkins_attendance_date_index` (`work_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
};

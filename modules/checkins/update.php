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

    if (version_compare($fromVersion, '1.3.0', '<')) {
        // إحداثيات الموقع عند الحضور والانصراف (اختيارية - بموافقة المتصفح)
        $colMissing = fn (string $c): bool => !$pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'checkins_attendance' AND column_name = " . $pdo->quote($c)
        )->fetchColumn();
        // الجدول قد لا يوجد بعد عند الترقية من <1.2.0 (يُنشأ بالأسفل بكل أعمدته)
        $tableExists = $pdo->query("SHOW TABLES LIKE 'checkins_attendance'")->fetch();
        if ($tableExists && $colMissing('in_lat')) {
            $pdo->exec("ALTER TABLE `checkins_attendance`
                ADD COLUMN `in_lat` DECIMAL(10,7) NULL AFTER `in_at`,
                ADD COLUMN `in_lng` DECIMAL(10,7) NULL AFTER `in_lat`,
                ADD COLUMN `out_lat` DECIMAL(10,7) NULL AFTER `out_at`,
                ADD COLUMN `out_lng` DECIMAL(10,7) NULL AFTER `out_lat`");
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
                `in_lat` DECIMAL(10,7) NULL,
                `in_lng` DECIMAL(10,7) NULL,
                `out_at` DATETIME NULL,
                `out_lat` DECIMAL(10,7) NULL,
                `out_lng` DECIMAL(10,7) NULL,
                UNIQUE KEY `checkins_attendance_unique` (`company_id`, `user_id`, `work_date`),
                KEY `checkins_attendance_date_index` (`work_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
};

<?php
/**
 * يُستدعى عند الضغط على "تحديث" إن كان إصدار القرص أحدث من إصدار قاعدة البيانات.
 */
return function (PDO $pdo, string $fromVersion): void {
    if (version_compare($fromVersion, '1.1.0', '<')) {
        $exists = $pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'archive_files' AND column_name = 'deleted_at'"
        )->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `archive_files` ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_by`, ADD KEY `archive_files_deleted_index` (`deleted_at`)");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `archive_tags` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(60) NOT NULL,
                `created_at` DATETIME NOT NULL,
                UNIQUE KEY `archive_tags_company_name_unique` (`company_id`, `name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `archive_file_tags` (
                `file_id` INT UNSIGNED NOT NULL,
                `tag_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`file_id`, `tag_id`),
                CONSTRAINT `archive_file_tags_file_fk` FOREIGN KEY (`file_id`) REFERENCES `archive_files`(`id`) ON DELETE CASCADE,
                CONSTRAINT `archive_file_tags_tag_fk` FOREIGN KEY (`tag_id`) REFERENCES `archive_tags`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `archive_file_shares` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `file_id` INT UNSIGNED NOT NULL,
                `token` VARCHAR(64) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `max_downloads` INT UNSIGNED NULL,
                `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `revoked_at` DATETIME NULL,
                `created_by` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL,
                UNIQUE KEY `archive_file_shares_token_unique` (`token`),
                KEY `archive_file_shares_file_index` (`file_id`),
                CONSTRAINT `archive_file_shares_file_fk` FOREIGN KEY (`file_id`) REFERENCES `archive_files`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    if (version_compare($fromVersion, '1.2.0', '<')) {
        // سياسة الاحتفاظ (retention) للامتثال: مدة + إجراء بعد انقضائها
        $colMissing = function (string $col) use ($pdo): bool {
            return !$pdo->query(
                "SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = 'archive_settings' AND column_name = " . $pdo->quote($col)
            )->fetchColumn();
        };
        if ($colMissing('retention_months')) {
            $pdo->exec("ALTER TABLE `archive_settings` ADD COLUMN `retention_months` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'مدة الاحتفاظ بالشهور (0 = معطّل)' AFTER `expiry_warning_days`");
        }
        if ($colMissing('retention_action')) {
            $pdo->exec("ALTER TABLE `archive_settings` ADD COLUMN `retention_action` ENUM('none','archive','trash') NOT NULL DEFAULT 'none' COMMENT 'الإجراء بعد انقضاء مدة الاحتفاظ' AFTER `retention_months`");
        }
    }

    if (version_compare($fromVersion, '1.3.0', '<')) {
        // لقطة اسم الكيان المرتبط (العمودان linked_module/linked_id موجودان أصلاً)
        $missing = !$pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'archive_files' AND column_name = 'linked_label'"
        )->fetchColumn();
        if ($missing) {
            $pdo->exec("ALTER TABLE `archive_files` ADD COLUMN `linked_label` VARCHAR(200) NULL COMMENT 'اسم الكيان المرتبط (لقطة)' AFTER `linked_id`");
        }
    }

    if (version_compare($fromVersion, '1.4.0', '<')) {
        // ربط متعدد: جدول جديد يستوعب عدة كيانات لكل ملف، مع ترحيل الربط الأحادي القديم.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `archive_file_links` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `file_id` INT UNSIGNED NOT NULL,
                `linked_module` VARCHAR(50) NOT NULL,
                `linked_id` INT UNSIGNED NOT NULL,
                `linked_label` VARCHAR(200) NULL,
                `created_at` DATETIME NOT NULL,
                UNIQUE KEY `archive_file_links_unique` (`file_id`, `linked_module`, `linked_id`),
                KEY `archive_file_links_file_index` (`file_id`),
                CONSTRAINT `archive_file_links_file_fk` FOREIGN KEY (`file_id`) REFERENCES `archive_files`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        // ترحيل الروابط الأحادية القائمة إلى الجدول الجديد (دون تكرار)
        $pdo->exec("
            INSERT IGNORE INTO `archive_file_links` (`file_id`, `linked_module`, `linked_id`, `linked_label`, `created_at`)
            SELECT `id`, `linked_module`, `linked_id`, `linked_label`, NOW()
              FROM `archive_files`
             WHERE `linked_module` IS NOT NULL AND `linked_id` IS NOT NULL
        ");
    }
};

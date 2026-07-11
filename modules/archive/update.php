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
};

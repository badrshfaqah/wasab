<?php
/**
 * تثبيت إضافة أرشيف الملفات: ينشئ جداولها الخاصة فقط، دون المساس بجداول النواة.
 * لا يوجد ربط FK بجداول users/companies عمداً (استقلالية الإضافة عن النواة)،
 * لكن الجداول الفرعية داخل نفس الإضافة (نسخ/سجلات/تحميلات/مستخدمو الوصول) مرتبطة
 * بـ FK لأنها ملك جدول الملفات نفسه ولا معنى لبقائها بعد حذفه.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `archive_categories` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `parent_id` INT UNSIGNED NULL,
            `name` VARCHAR(150) NOT NULL,
            `visibility_type` ENUM('all','specific_users','system_admin_only','company_admin_only') NOT NULL DEFAULT 'all',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `archive_categories_company_index` (`company_id`),
            KEY `archive_categories_parent_index` (`parent_id`),
            CONSTRAINT `archive_categories_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `archive_categories`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `archive_category_users` (
            `category_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`category_id`, `user_id`),
            CONSTRAINT `archive_category_users_category_fk` FOREIGN KEY (`category_id`) REFERENCES `archive_categories`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `archive_files` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `category_id` INT UNSIGNED NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `stored_name` VARCHAR(255) NOT NULL,
            `extension` VARCHAR(10) NOT NULL,
            `mime_type` VARCHAR(100) NOT NULL,
            `size` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NULL,
            `description` TEXT NULL,
            `keywords` VARCHAR(500) NULL,
            `version` INT UNSIGNED NOT NULL DEFAULT 1,
            `status` ENUM('active','expired','archived','closed') NOT NULL DEFAULT 'active',
            `visibility_type` ENUM('inherit','all','specific_users','system_admin_only','company_admin_only') NOT NULL DEFAULT 'inherit',
            `expires_at` DATE NULL,
            `notes` TEXT NULL,
            `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `linked_module` VARCHAR(50) NULL COMMENT 'مستقبلي: ربط الملف بكيان من إضافة أخرى (مهام/عقود...)',
            `linked_id` INT UNSIGNED NULL,
            `created_by` INT UNSIGNED NOT NULL,
            `updated_by` INT UNSIGNED NULL,
            `deleted_at` DATETIME NULL COMMENT 'حذف ناعم (سلة المحذوفات) - الملف والصف يبقيان حتى الاستعادة أو الحذف النهائي',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `archive_files_company_index` (`company_id`),
            KEY `archive_files_category_index` (`category_id`),
            KEY `archive_files_status_index` (`status`),
            KEY `archive_files_expires_index` (`expires_at`),
            KEY `archive_files_creator_index` (`created_by`),
            KEY `archive_files_deleted_index` (`deleted_at`),
            CONSTRAINT `archive_files_category_fk` FOREIGN KEY (`category_id`) REFERENCES `archive_categories`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `archive_file_users` (
            `file_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`file_id`, `user_id`),
            CONSTRAINT `archive_file_users_file_fk` FOREIGN KEY (`file_id`) REFERENCES `archive_files`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `archive_file_versions` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `file_id` INT UNSIGNED NOT NULL,
            `version` INT UNSIGNED NOT NULL,
            `stored_name` VARCHAR(255) NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `size` INT UNSIGNED NOT NULL,
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            KEY `archive_file_versions_file_index` (`file_id`),
            CONSTRAINT `archive_file_versions_file_fk` FOREIGN KEY (`file_id`) REFERENCES `archive_files`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `archive_file_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `file_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `action` VARCHAR(50) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `created_at` DATETIME NOT NULL,
            KEY `archive_file_logs_file_index` (`file_id`),
            CONSTRAINT `archive_file_logs_file_fk` FOREIGN KEY (`file_id`) REFERENCES `archive_files`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `archive_file_downloads` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `file_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            KEY `archive_file_downloads_file_index` (`file_id`),
            CONSTRAINT `archive_file_downloads_file_fk` FOREIGN KEY (`file_id`) REFERENCES `archive_files`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `archive_settings` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `expiry_warning_days` INT UNSIGNED NOT NULL DEFAULT 7,
            `retention_months` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'مدة الاحتفاظ بالشهور (0 = معطّل)',
            `retention_action` ENUM('none','archive','trash') NOT NULL DEFAULT 'none' COMMENT 'الإجراء بعد انقضاء مدة الاحتفاظ',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `archive_settings_company_unique` (`company_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};

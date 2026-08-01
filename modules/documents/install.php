<?php
/**
 * تثبيت إضافة المستندات: ينشئ جداولها الخاصة فقط، دون المساس بجداول النواة.
 * لا يوجد ربط FK بجداول users/companies عمداً، لتبقى الإضافة مستقلة تماماً
 * ويسهل إزالتها دون التأثير على النواة.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `documents_templates` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `background_image` VARCHAR(255) NULL,
            `header_html` TEXT NULL,
            `footer_html` TEXT NULL,
            `number_position` ENUM('top-right','top-left','bottom-right','bottom-left') NOT NULL DEFAULT 'top-right',
            `show_date` TINYINT(1) NOT NULL DEFAULT 1,
            `show_number` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `documents_templates_company_index` (`company_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `documents_documents` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `type` ENUM('general','letter','decision','certificate','authorization') NOT NULL DEFAULT 'general',
            `visibility` ENUM('public','private') NOT NULL DEFAULT 'public',
            `confidentiality` ENUM('normal','internal','confidential','secret') NOT NULL DEFAULT 'normal' COMMENT 'تصنيف السرية (عادي/داخلي/سري/سري للغاية)',
            `status` ENUM('draft','pending_approval','approved','signed','archived') NOT NULL DEFAULT 'draft',
            `previous_status` ENUM('draft','pending_approval','approved','signed') NULL COMMENT 'الحالة قبل الأرشفة، لاستعادة المستند إليها',
            `number` VARCHAR(50) NULL,
            `title` VARCHAR(255) NOT NULL,
            `content` LONGTEXT NULL,
            `template_id` INT UNSIGNED NULL,
            `follow_up_date` DATE NULL COMMENT 'تاريخ متابعة اختياري - يظهر كحدث بالتقويم الموحّد',
            `expiry_date` DATE NULL COMMENT 'تاريخ انتهاء صلاحية المستند (اختياري)',
            `verify_token` CHAR(32) NULL COMMENT 'رمز تحقق عام غير قابل للتخمين للتأكد من صحة المستند',
            `created_by` INT UNSIGNED NOT NULL,
            `approved_by` INT UNSIGNED NULL,
            `approved_at` DATETIME NULL,
            `signed_by` INT UNSIGNED NULL,
            `signed_at` DATETIME NULL,
            `archived_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `documents_company_index` (`company_id`),
            KEY `documents_status_index` (`status`),
            KEY `documents_creator_index` (`created_by`),
            KEY `documents_template_index` (`template_id`),
            KEY `documents_follow_up_index` (`follow_up_date`),
            UNIQUE KEY `documents_company_number_unique` (`company_id`, `number`),
            UNIQUE KEY `documents_verify_token_unique` (`verify_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `documents_versions` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `document_id` INT UNSIGNED NOT NULL,
            `version_no` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `content` LONGTEXT NULL,
            `saved_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            KEY `documents_versions_document_index` (`document_id`),
            CONSTRAINT `documents_versions_document_fk` FOREIGN KEY (`document_id`) REFERENCES `documents_documents`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `documents_settings` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `number_prefix` VARCHAR(20) NOT NULL DEFAULT 'DOC',
            `last_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
            `signature_image` VARCHAR(255) NULL,
            `stamp_image` VARCHAR(255) NULL,
            `signer_name` VARCHAR(150) NULL,
            `signer_title` VARCHAR(150) NULL,
            `header_html` TEXT NULL,
            `footer_html` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `documents_settings_company_unique` (`company_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `documents_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `document_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `action` VARCHAR(50) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `created_at` DATETIME NOT NULL,
            KEY `documents_logs_document_index` (`document_id`),
            CONSTRAINT `documents_logs_document_fk` FOREIGN KEY (`document_id`) REFERENCES `documents_documents`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};

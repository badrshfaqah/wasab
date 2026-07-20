<?php
/**
 * تثبيت إضافة مركز المراسلات: ينشئ جداولها الخاصة فقط، دون المساس بجداول النواة.
 * لا يوجد ربط FK بجداول users/companies عمداً، لتبقى الإضافة مستقلة تماماً
 * ويسهل إزالتها دون التأثير على النواة.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `inbox_sites` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `url` VARCHAR(255) NULL,
            `platform` VARCHAR(30) NOT NULL DEFAULT 'custom',
            `api_key` CHAR(64) NOT NULL,
            `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
            `last_message_at` DATETIME NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `inbox_sites_api_key_unique` (`api_key`),
            KEY `inbox_sites_company_index` (`company_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `inbox_messages` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `site_id` INT UNSIGNED NOT NULL,
            `sender_name` VARCHAR(150) NULL,
            `sender_email` VARCHAR(190) NULL,
            `sender_phone` VARCHAR(50) NULL,
            `subject` VARCHAR(255) NULL,
            `body` TEXT NOT NULL,
            `extra` TEXT NULL,
            `source_ip` VARCHAR(45) NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `read_by` INT UNSIGNED NULL,
            `read_at` DATETIME NULL,
            `received_at` DATETIME NOT NULL,
            KEY `inbox_messages_company_index` (`company_id`),
            KEY `inbox_messages_site_index` (`site_id`),
            KEY `inbox_messages_is_read_index` (`is_read`),
            KEY `inbox_messages_received_index` (`received_at`),
            CONSTRAINT `inbox_messages_site_fk` FOREIGN KEY (`site_id`) REFERENCES `inbox_sites`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};

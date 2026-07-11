<?php
/**
 * تثبيت إضافة التلفون: جداولها الخاصة فقط، بدون أي ربط FK بجداول النواة
 * حتى تبقى الإضافة مستقلة تماماً ويسهل إزالتها لاحقاً دون التأثير على النواة.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `phone_company_settings` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `api_key` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `phone_company_settings_company_unique` (`company_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `phone_users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `extension` VARCHAR(50) NOT NULL,
            `secret` VARCHAR(255) NOT NULL,
            `enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `phone_users_user_unique` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `phone_contacts` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `type` ENUM('internal','external') NOT NULL,
            `linked_user_id` INT UNSIGNED NULL COMMENT 'للنوع الداخلي فقط: يشير لمستخدم بنفس الشركة، والرقم يُقرأ حياً من phone_users عنده وقت العرض',
            `name` VARCHAR(150) NOT NULL,
            `phone_number` VARCHAR(50) NULL COMMENT 'للنوع الخارجي فقط',
            `notes` VARCHAR(255) NULL,
            `visibility` ENUM('public','private') NOT NULL DEFAULT 'private',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `phone_contacts_company_index` (`company_id`),
            KEY `phone_contacts_visibility_index` (`visibility`),
            KEY `phone_contacts_creator_index` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};

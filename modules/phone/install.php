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
};

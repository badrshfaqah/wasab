<?php
/** تثبيت إضافة العملاء: سجل العملاء ببيانات التواصل. */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `clients_clients` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `type` ENUM('company','person') NOT NULL DEFAULT 'company',
            `contact_name` VARCHAR(150) NULL COMMENT 'الشخص المسؤول (للشركات)',
            `phone` VARCHAR(50) NULL,
            `email` VARCHAR(150) NULL,
            `address` VARCHAR(300) NULL,
            `notes` TEXT NULL,
            `status` ENUM('active','archived') NOT NULL DEFAULT 'active',
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `clients_company_index` (`company_id`),
            KEY `clients_status_index` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};

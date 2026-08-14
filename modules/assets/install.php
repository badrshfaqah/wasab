<?php
/**
 * تثبيت إضافة العهد والأصول: جداولها الخاصة فقط، دون FK إلزامي على جداول النواة
 * (users/companies) أو إضافة الملف الوظيفي - لتبقى مستقلة وتعمل حتى لو عُطّلت تلك.
 * حامل العهدة polymorphic: نوع + معرّف + لقطة اسم، فيبقى السجل صحيحاً حتى لو حُذف
 * الموظف/المستخدم لاحقاً (نفس فلسفة استقلال الملف الوظيفي عن حساب الدخول).
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `assets_categories` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `fields_json` TEXT NULL COMMENT 'أسماء الحقول المخصصة لهذا التصنيف (JSON)',
            `created_at` DATETIME NOT NULL,
            KEY `assets_categories_company_index` (`company_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `assets_assets` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `category_id` INT UNSIGNED NULL,
            `name` VARCHAR(180) NOT NULL,
            `asset_code` VARCHAR(80) NULL COMMENT 'رمز داخلي/باركود يحدده مدير الشركة',
            `serial_number` VARCHAR(120) NULL,
            `status` ENUM('available','assigned','maintenance','retired','lost') NOT NULL DEFAULT 'available',
            `condition_note` VARCHAR(60) NULL COMMENT 'حالة مادية: جديد/جيد/مستعمل...',
            `purchase_date` DATE NULL,
            `purchase_cost` DECIMAL(12,2) NULL,
            `warranty_expiry` DATE NULL COMMENT 'يظهر كتنبيه بالتقويم الموحّد',
            `photo` VARCHAR(255) NULL,
            `notes` TEXT NULL,
            `custom_json` TEXT NULL COMMENT 'قيم الحقول المخصصة (JSON)',
            `current_holder_type` ENUM('employee','user','manual') NULL,
            `current_holder_ref` INT UNSIGNED NULL COMMENT 'معرّف الموظف/المستخدم (فارغ للـ manual)',
            `current_holder_name` VARCHAR(180) NULL COMMENT 'لقطة اسم الحامل الحالي للعرض السريع',
            `assigned_at` DATETIME NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `assets_assets_company_index` (`company_id`),
            KEY `assets_assets_status_index` (`status`),
            KEY `assets_assets_category_index` (`category_id`),
            KEY `assets_assets_warranty_index` (`warranty_expiry`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // محضر تسليم: عملية إسناد واحدة قد تضم عدة أصول لنفس الحامل
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `assets_handovers` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `holder_type` ENUM('employee','user','manual') NOT NULL,
            `holder_ref` INT UNSIGNED NULL,
            `holder_name` VARCHAR(180) NOT NULL COMMENT 'لقطة اسم الحامل وقت المحضر',
            `holder_contact` VARCHAR(120) NULL COMMENT 'للحامل اليدوي: جوال/بريد',
            `handover_date` DATE NOT NULL,
            `notes` TEXT NULL,
            `acknowledged_at` DATETIME NULL COMMENT 'وقت إقرار الحامل (المربوط بحساب) باستلام العهدة',
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            KEY `assets_handovers_company_index` (`company_id`),
            KEY `assets_handovers_holder_index` (`holder_type`, `holder_ref`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // بنود المحضر: كل أصل في المحضر، وحالة إرجاعه لاحقاً
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `assets_handover_items` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `handover_id` INT UNSIGNED NOT NULL,
            `asset_id` INT UNSIGNED NOT NULL,
            `returned_at` DATETIME NULL,
            `return_condition` VARCHAR(60) NULL,
            `return_note` VARCHAR(255) NULL,
            KEY `assets_handover_items_handover_index` (`handover_id`),
            KEY `assets_handover_items_asset_index` (`asset_id`),
            CONSTRAINT `assets_handover_items_handover_fk` FOREIGN KEY (`handover_id`) REFERENCES `assets_handovers`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // سجل حركة كل أصل (تسليم/إرجاع/تغيير حالة/صيانة...) للتتبع الكامل
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `assets_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `asset_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `action` VARCHAR(40) NOT NULL,
            `description` VARCHAR(400) NULL,
            `created_at` DATETIME NOT NULL,
            KEY `assets_logs_asset_index` (`asset_id`),
            KEY `assets_logs_created_index` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};

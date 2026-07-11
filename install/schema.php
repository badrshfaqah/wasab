<?php
/**
 * تعريف جداول النواة الأساسية. يُنفَّذ مرة واحدة من معالج التثبيت.
 * يعيد مصفوفة من جمل SQL بالترتيب الصحيح لمراعاة المفاتيح الأجنبية.
 */
return [

'companies' => "
CREATE TABLE IF NOT EXISTS `companies` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `logo` VARCHAR(255) NULL,
    `primary_color` VARCHAR(7) NOT NULL DEFAULT '#2563eb',
    `sidebar_color` VARCHAR(7) NOT NULL DEFAULT '#111827',
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
",

'users' => "
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NULL,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `membership_type` ENUM('system_admin','company_admin','user') NOT NULL DEFAULT 'user',
    `avatar` VARCHAR(255) NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME NULL,
    `failed_login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL,
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `users_company_id_index` (`company_id`),
    CONSTRAINT `users_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
",

'roles' => "
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NULL,
    `name` VARCHAR(100) NOT NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    KEY `roles_company_id_index` (`company_id`),
    CONSTRAINT `roles_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
",

'permissions' => "
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module_key` VARCHAR(50) NOT NULL,
    `permission_key` VARCHAR(150) NOT NULL,
    `label` VARCHAR(150) NOT NULL,
    `default_level` ENUM('employee','manager') NOT NULL DEFAULT 'employee' COMMENT 'تصنيف إرشادي فقط (لا يُطبَّق تلقائياً) يساعد عند إنشاء أدوار جديدة مستقبلاً',
    UNIQUE KEY `permissions_key_unique` (`permission_key`),
    KEY `permissions_module_index` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
",

'role_permissions' => "
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `role_permissions_permission_index` (`permission_id`),
    CONSTRAINT `role_permissions_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    CONSTRAINT `role_permissions_permission_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
",

'user_roles' => "
CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_id` INT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    KEY `user_roles_role_index` (`role_id`),
    CONSTRAINT `user_roles_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `user_roles_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
",

'modules' => "
CREATE TABLE IF NOT EXISTS `modules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module_key` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    `installed_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL,
    UNIQUE KEY `modules_key_unique` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
",

'settings' => "
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NULL,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    UNIQUE KEY `settings_scope_unique` (`company_id`, `setting_key`),
    CONSTRAINT `settings_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
",

'notifications' => "
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NULL,
    `url` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    KEY `notifications_user_read_index` (`user_id`, `is_read`),
    CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
",

'activity_log' => "
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `company_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `subject_type` VARCHAR(50) NULL,
    `subject_id` VARCHAR(50) NULL,
    `description` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL,
    KEY `activity_log_company_index` (`company_id`),
    KEY `activity_log_user_index` (`user_id`),
    KEY `activity_log_created_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
",

];

<?php
/**
 * تثبيت إضافة المهام: ينشئ جداولها الخاصة فقط، دون المساس بجداول النواة.
 * لا يوجد ربط FK بجداول users/companies عمداً، لتبقى الإضافة مستقلة تماماً
 * ويسهل إزالتها دون التأثير على النواة.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tasks_tasks` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT NULL,
            `assignee_id` INT UNSIGNED NOT NULL,
            `creator_id` INT UNSIGNED NOT NULL,
            `approver_id` INT UNSIGNED NULL,
            `start_date` DATE NULL,
            `due_date` DATE NULL,
            `priority` ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
            `status` ENUM('todo','in_progress','in_review','done','cancelled') NOT NULL DEFAULT 'todo',
            `requires_approval` TINYINT(1) NOT NULL DEFAULT 0,
            `approved_at` DATETIME NULL,
            `completed_at` DATETIME NULL COMMENT 'وقت إتمام المهمة (حالة done) - لقياس الالتزام بالمواعيد',
            `escalated_at` DATETIME NULL COMMENT 'وقت تصعيد المهمة المتأخرة (مرة واحدة لكل موعد استحقاق)',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `tasks_company_index` (`company_id`),
            KEY `tasks_assignee_index` (`assignee_id`),
            KEY `tasks_creator_index` (`creator_id`),
            KEY `tasks_status_index` (`status`),
            KEY `tasks_due_date_index` (`due_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tasks_comments` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `task_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `body` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            KEY `tasks_comments_task_index` (`task_id`),
            CONSTRAINT `tasks_comments_task_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks_tasks`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tasks_attachments` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `task_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `stored_name` VARCHAR(255) NOT NULL,
            `size` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            KEY `tasks_attachments_task_index` (`task_id`),
            CONSTRAINT `tasks_attachments_task_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks_tasks`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tasks_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `task_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `action` VARCHAR(50) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `created_at` DATETIME NOT NULL,
            KEY `tasks_logs_task_index` (`task_id`),
            CONSTRAINT `tasks_logs_task_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks_tasks`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tasks_subtasks` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `task_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `is_done` TINYINT(1) NOT NULL DEFAULT 0,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `tasks_subtasks_task_index` (`task_id`),
            CONSTRAINT `tasks_subtasks_task_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks_tasks`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tasks_recurring` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT NULL,
            `assignee_id` INT UNSIGNED NOT NULL,
            `priority` ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
            `frequency` ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
            `due_offset_days` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'أيام حتى الاستحقاق بعد توليد المهمة',
            `next_run` DATE NOT NULL COMMENT 'تاريخ توليد المهمة القادمة',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            KEY `tasks_recurring_company_index` (`company_id`),
            KEY `tasks_recurring_next_run_index` (`next_run`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tasks_checklists` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(160) NOT NULL,
            `items` TEXT NOT NULL COMMENT 'عناصر القائمة، عنصر لكل سطر',
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            KEY `tasks_checklists_company_index` (`company_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};

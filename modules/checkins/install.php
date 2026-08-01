<?php
/**
 * تثبيت إضافة المتابعة اليومية: جداولها الخاصة فقط. لا FK على جداول النواة أو
 * إضافة المهام عمداً - الربط بالمهام منطقي (بالمعرّفات) حتى تبقى الإضافة قابلة
 * للتعطيل والإزالة باستقلال تام، ويصمد السجل حتى لو أُزيلت إضافة المهام.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `checkins_entries` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `entry_date` DATE NOT NULL,
            `mood` TINYINT UNSIGNED NULL COMMENT 'معنويات اليوم: 1 (مرهق) .. 5 (ممتاز) - اختياري',
            `done_text` TEXT NULL,
            `plan_text` TEXT NULL,
            `blockers_text` TEXT NULL,
            `blocker_task_id` INT UNSIGNED NULL COMMENT 'معرّف المهمة التي حُوّل إليها المعوق (إن حُوّل)',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `checkins_user_date_unique` (`user_id`, `entry_date`),
            KEY `checkins_company_date_index` (`company_id`, `entry_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `checkins_entry_tasks` (
            `entry_id` INT UNSIGNED NOT NULL,
            `task_id` INT UNSIGNED NOT NULL,
            `kind` ENUM('done','planned') NOT NULL,
            PRIMARY KEY (`entry_id`, `task_id`, `kind`),
            CONSTRAINT `checkins_entry_tasks_entry_fk` FOREIGN KEY (`entry_id`) REFERENCES `checkins_entries`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};

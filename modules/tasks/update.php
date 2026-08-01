<?php
/**
 * يُستدعى عند الضغط على "تحديث" إن كان إصدار القرص أحدث من إصدار قاعدة البيانات.
 */
return function (PDO $pdo, string $fromVersion): void {
    if (version_compare($fromVersion, '1.1.0', '<')) {
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
    }

    if (version_compare($fromVersion, '1.2.0', '<')) {
        $colMissing = function (string $col) use ($pdo): bool {
            return !$pdo->query(
                "SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = 'tasks_tasks' AND column_name = " . $pdo->quote($col)
            )->fetchColumn();
        };
        if ($colMissing('completed_at')) {
            $pdo->exec("ALTER TABLE `tasks_tasks` ADD COLUMN `completed_at` DATETIME NULL COMMENT 'وقت إتمام المهمة - لقياس الالتزام' AFTER `approved_at`");
            // تقدير وقت الإتمام للمهام المكتملة سابقاً بوقت آخر تعديل
            $pdo->exec("UPDATE `tasks_tasks` SET `completed_at` = COALESCE(`updated_at`, `created_at`) WHERE `status` = 'done' AND `completed_at` IS NULL");
        }
        if ($colMissing('escalated_at')) {
            $pdo->exec("ALTER TABLE `tasks_tasks` ADD COLUMN `escalated_at` DATETIME NULL COMMENT 'وقت تصعيد التأخر' AFTER `completed_at`");
        }
    }

    if (version_compare($fromVersion, '1.3.0', '<')) {
        // المهام المتكررة (توليد دوري عبر cron)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tasks_recurring` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT NULL,
                `assignee_id` INT UNSIGNED NOT NULL,
                `priority` ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
                `frequency` ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
                `due_offset_days` INT UNSIGNED NOT NULL DEFAULT 0,
                `next_run` DATE NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                KEY `tasks_recurring_company_index` (`company_id`),
                KEY `tasks_recurring_next_run_index` (`next_run`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    if (version_compare($fromVersion, '1.5.0', '<')) {
        $lc = function (string $col) use ($pdo): bool {
            return !$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tasks_tasks' AND column_name = " . $pdo->quote($col))->fetchColumn();
        };
        if ($lc('linked_type')) {
            $pdo->exec("ALTER TABLE `tasks_tasks` ADD COLUMN `linked_type` VARCHAR(20) NULL AFTER `escalated_at`");
            $pdo->exec("ALTER TABLE `tasks_tasks` ADD COLUMN `linked_id` INT UNSIGNED NULL AFTER `linked_type`");
            $pdo->exec("ALTER TABLE `tasks_tasks` ADD COLUMN `linked_label` VARCHAR(200) NULL AFTER `linked_id`");
        }
    }

    if (version_compare($fromVersion, '1.4.0', '<')) {
        // قوالب قوائم التحقق (Checklists)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tasks_checklists` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(160) NOT NULL,
                `items` TEXT NOT NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                KEY `tasks_checklists_company_index` (`company_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
};

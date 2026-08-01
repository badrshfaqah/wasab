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
};

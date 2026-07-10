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
};

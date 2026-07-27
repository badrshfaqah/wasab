<?php
/**
 * إزالة نهائية للإضافة: تُستدعى فقط عند الضغط على "إزالة" بعد التعطيل.
 * تحذف جداول الإضافة وكل بياناتها.
 */
return function (PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DROP TABLE IF EXISTS `tasks_logs`');
    $pdo->exec('DROP TABLE IF EXISTS `tasks_attachments`');
    $pdo->exec('DROP TABLE IF EXISTS `tasks_comments`');
    $pdo->exec('DROP TABLE IF EXISTS `tasks_subtasks`');
    $pdo->exec('DROP TABLE IF EXISTS `tasks_tasks`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

<?php
/**
 * إزالة نهائية للإضافة: تُستدعى فقط عند الضغط على "إزالة" بعد التعطيل.
 * تحذف جداول الإضافة وكل بياناتها.
 */
return function (PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DROP TABLE IF EXISTS `documents_logs`');
    $pdo->exec('DROP TABLE IF EXISTS `documents_documents`');
    $pdo->exec('DROP TABLE IF EXISTS `documents_settings`');
    $pdo->exec('DROP TABLE IF EXISTS `documents_templates`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

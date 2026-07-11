<?php
/**
 * إزالة نهائية للإضافة: تُستدعى فقط عند الضغط على "إزالة" بعد التعطيل.
 * تحذف جداول الإضافة وكل بياناتها.
 */
return function (PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DROP TABLE IF EXISTS `meetings_notes`');
    $pdo->exec('DROP TABLE IF EXISTS `meetings_attendees`');
    $pdo->exec('DROP TABLE IF EXISTS `meetings_meetings`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

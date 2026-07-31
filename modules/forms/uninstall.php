<?php
/** إزالة نهائية: تحذف كل جداول الإضافة. */
return function (PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DROP TABLE IF EXISTS `forms_letters`');
    $pdo->exec('DROP TABLE IF EXISTS `forms_templates`');
    $pdo->exec('DROP TABLE IF EXISTS `forms_settings`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

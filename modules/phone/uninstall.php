<?php
return function (PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DROP TABLE IF EXISTS `phone_contacts`');
    $pdo->exec('DROP TABLE IF EXISTS `phone_users`');
    $pdo->exec('DROP TABLE IF EXISTS `phone_company_settings`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

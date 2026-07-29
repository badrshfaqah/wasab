<?php
/** إزالة نهائية: تحذف كل جداول الإضافة (بترتيب يراعي المفاتيح الأجنبية). */
return function (PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DROP TABLE IF EXISTS `assets_handover_items`');
    $pdo->exec('DROP TABLE IF EXISTS `assets_handovers`');
    $pdo->exec('DROP TABLE IF EXISTS `assets_logs`');
    $pdo->exec('DROP TABLE IF EXISTS `assets_assets`');
    $pdo->exec('DROP TABLE IF EXISTS `assets_categories`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

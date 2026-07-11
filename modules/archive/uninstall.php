<?php
/**
 * إزالة نهائية للإضافة: تُستدعى فقط عند الضغط على "إزالة" بعد التعطيل.
 * تحذف جداول الإضافة وكل بياناتها، بالإضافة إلى الملفات الفعلية المرفوعة على القرص.
 */
return function (PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DROP TABLE IF EXISTS `archive_file_shares`');
    $pdo->exec('DROP TABLE IF EXISTS `archive_file_tags`');
    $pdo->exec('DROP TABLE IF EXISTS `archive_tags`');
    $pdo->exec('DROP TABLE IF EXISTS `archive_file_downloads`');
    $pdo->exec('DROP TABLE IF EXISTS `archive_file_logs`');
    $pdo->exec('DROP TABLE IF EXISTS `archive_file_versions`');
    $pdo->exec('DROP TABLE IF EXISTS `archive_file_users`');
    $pdo->exec('DROP TABLE IF EXISTS `archive_files`');
    $pdo->exec('DROP TABLE IF EXISTS `archive_category_users`');
    $pdo->exec('DROP TABLE IF EXISTS `archive_categories`');
    $pdo->exec('DROP TABLE IF EXISTS `archive_settings`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $dir = BASE_PATH . '/storage/uploads/archive';
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
};

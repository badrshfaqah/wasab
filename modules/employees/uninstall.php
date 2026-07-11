<?php
/**
 * إزالة نهائية للإضافة: تُستدعى فقط عند الضغط على "إزالة" بعد التعطيل.
 * تحذف جداول الإضافة وكل بياناتها، بالإضافة إلى الملفات الفعلية المرفوعة على القرص.
 */
return function (PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DROP TABLE IF EXISTS `employees_timeline`');
    $pdo->exec('DROP TABLE IF EXISTS `employees_documents`');
    $pdo->exec('DROP TABLE IF EXISTS `employees_certifications`');
    $pdo->exec('DROP TABLE IF EXISTS `employees_dependents`');
    $pdo->exec('DROP TABLE IF EXISTS `employees_profiles`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $dir = BASE_PATH . '/storage/uploads/employees';
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
};

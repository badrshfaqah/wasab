<?php
/** إزالة نهائية: حذف جداول الإضافة فقط (الصور تبقى في storage للنسخ الاحتياطي). */
return function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS `expenses_claims`');
};

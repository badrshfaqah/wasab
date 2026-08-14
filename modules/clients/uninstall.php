<?php
/** إزالة نهائية: حذف جداول الإضافة فقط. */
return function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS `clients_clients`');
};
